-- One-time import of the legacy production database (pre-reset migration
-- history) into a freshly migrated database of this release line.
--
-- Run inside the target database as a superuser, through
-- deploy/cutover/import-legacy-database.sh. Everything happens in one
-- transaction: any failed check rolls the whole import back.
--
-- Variables: source_db, order_seq_last, order_seq_called.

\set ON_ERROR_STOP on
set client_min_messages = warning;

begin;

set local app.current_restaurant_id = '1';

create extension if not exists postgres_fdw;
create server legacy_source foreign data wrapper postgres_fdw options (dbname :'source_db');
do $$
begin
    execute format('create user mapping for current_user server legacy_source options (user %L)', current_user);
end
$$;
create schema legacy;
import foreign schema public from server legacy_source into legacy;

-- Guards -------------------------------------------------------------------

do $$
declare
    target record;
    populated boolean;
begin
    -- Only rows seeded by this release's migrations may exist in the target.
    for target in
        select tablename from pg_tables
        where schemaname = 'public'
          and tablename not in (
              'migrations', 'restaurants', 'inventory_units', 'order_settings', 'order_addons',
              'management_outflow_categories', 'instagram_automation_settings', 'cache', 'cache_locks', 'sessions'
          )
    loop
        execute format('select exists (select 1 from public.%I)', target.tablename) into populated;
        if populated then
            raise exception 'target database already holds data in %; import only into a freshly migrated database', target.tablename;
        end if;
    end loop;

    if exists (
        select 1 from legacy.products
        where unit_price is null or half_dozen_price is null or dozen_price is null
    ) then
        raise exception 'legacy products without all three price tiers cannot be classified; fix the catalog first';
    end if;

    if exists (
        select id, code, family, is_base, is_system from legacy.inventory_units
        except
        select id, code, family, is_base, is_system from public.inventory_units
    ) then
        raise exception 'legacy inventory units differ from the system units of this release';
    end if;
end
$$;

-- Copy ----------------------------------------------------------------------

create temporary table import_plan (
    table_name text primary key,
    columns text not null
) on commit drop;

-- Tables copied column-by-column (intersection of legacy and target columns).
insert into import_plan (table_name, columns)
select t.table_name,
       string_agg(quote_ident(c.column_name), ', ' order by c.ordinal_position)
from information_schema.tables t
join information_schema.columns c
  on c.table_schema = 'public' and c.table_name = t.table_name
join information_schema.columns l
  on l.table_schema = 'legacy' and l.table_name = t.table_name and l.column_name = c.column_name
where t.table_schema = 'public'
  and t.table_type = 'BASE TABLE'
  and t.table_name not in (
      'migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs',
      'password_reset_tokens', 'restaurants', 'inventory_units',
      'inventory_costing_policy_packaging_components', 'inventory_costing_policy_presentations'
  )
group by t.table_name;

-- Seed rows created by this release's migrations; re-added if the legacy data lacks them.
create temporary table import_seeds on commit drop as
select 'order_settings'::text as table_name, to_jsonb(s) - 'id' - 'restaurant_id' as row from public.order_settings s
union all
select 'order_addons', to_jsonb(s) - 'id' - 'restaurant_id' from public.order_addons s
union all
select 'management_outflow_categories', to_jsonb(s) - 'id' - 'restaurant_id' from public.management_outflow_categories s;

-- Check constraints added NOT VALID exempt legacy rows in production; keep that exemption.
create temporary table import_not_valid_checks on commit drop as
select c.conrelid::regclass::text as table_name, c.conname, pg_get_constraintdef(c.oid) as definition
from pg_constraint c
join pg_namespace n on n.oid = c.connamespace
where n.nspname = 'public' and c.contype = 'c' and not c.convalidated;

do $$
declare
    check_constraint record;
begin
    for check_constraint in select * from import_not_valid_checks loop
        execute format('alter table %s drop constraint %I', check_constraint.table_name, check_constraint.conname);
    end loop;
end
$$;

-- Bulk load without row triggers or FK checks; integrity is verified below.
set local session_replication_role = replica;

do $$
declare
    plan record;
begin
    for plan in select * from import_plan loop
        execute format('delete from public.%I', plan.table_name);
        execute format(
            'insert into public.%I (%s) select %s from legacy.%I',
            plan.table_name, plan.columns, plan.columns, plan.table_name
        );
    end loop;
end
$$;

-- Packaging moved from (policy, commercial quantity) rows to presentations.
insert into public.inventory_costing_policy_presentations
    (costing_policy_id, commercial_quantity, packaging_mode, created_at, updated_at)
select costing_policy_id, commercial_quantity, 'components', min(created_at), max(updated_at)
from legacy.inventory_costing_policy_packaging_components
group by costing_policy_id, commercial_quantity;

insert into public.inventory_costing_policy_packaging_components
    (id, presentation_id, inventory_item_id, quantity, unit_id, base_quantity, created_at, updated_at)
select c.id, p.id, c.inventory_item_id, c.quantity, c.unit_id, c.base_quantity, c.created_at, c.updated_at
from legacy.inventory_costing_policy_packaging_components c
join public.inventory_costing_policy_presentations p
  on p.costing_policy_id = c.costing_policy_id and p.commercial_quantity = c.commercial_quantity;

set local session_replication_role = origin;

do $$
declare
    check_constraint record;
begin
    for check_constraint in select * from import_not_valid_checks loop
        -- pg_get_constraintdef already ends with NOT VALID.
        execute format('alter table %s add constraint %I %s', check_constraint.table_name, check_constraint.conname, check_constraint.definition);
    end loop;
end
$$;

-- Sequences.
do $$
declare
    seq record;
begin
    for seq in
        select c.table_name, c.column_name, pg_get_serial_sequence(format('public.%I', c.table_name), c.column_name) as sequence_name
        from information_schema.columns c
        where c.table_schema = 'public'
          and c.data_type in ('smallint', 'integer', 'bigint')
          and pg_get_serial_sequence(format('public.%I', c.table_name), c.column_name) is not null
    loop
        execute format(
            'select setval(%L, coalesce((select max(%I) from public.%I), 0) + 1, false)',
            seq.sequence_name, seq.column_name, seq.table_name
        );
    end loop;
end
$$;

\o /dev/null
select setval('public.order_public_id_seq', :order_seq_last, :'order_seq_called'::boolean);
\o

do $$
declare
    seed record;
begin
    for seed in select * from import_seeds loop
        execute format(
            'insert into public.%I select * from jsonb_populate_record(null::public.%I, $1 || jsonb_build_object(''id'', nextval(pg_get_serial_sequence(%L, ''id'')), ''restaurant_id'', 1)) on conflict do nothing',
            seed.table_name, seed.table_name, 'public.' || seed.table_name
        ) using seed.row;
    end loop;
end
$$;

-- Verification ------------------------------------------------------------

create temporary table import_report (
    table_name text primary key,
    legacy_rows bigint not null,
    imported_rows bigint not null,
    legacy_hash text,
    imported_hash text
) on commit drop;

do $$
declare
    plan record;
    cast_columns text;
    v_legacy_rows bigint;
    v_imported_rows bigint;
    v_legacy_hash text;
    v_imported_hash text;
begin
    for plan in select * from import_plan loop
        -- Compare on the target column types so timestamp precision/zone casts do not differ.
        select string_agg(format('%I::%s', a.attname, format_type(a.atttypid, a.atttypmod)), ', ' order by a.attnum)
          into cast_columns
          from pg_attribute a
         where a.attrelid = format('public.%I', plan.table_name)::regclass
           and a.attnum > 0 and not a.attisdropped
           and quote_ident(a.attname) = any (string_to_array(plan.columns, ', '));

        execute format('select count(*), md5(string_agg(r::text, '','' order by r::text)) from (select %s from legacy.%I) r', cast_columns, plan.table_name)
           into v_legacy_rows, v_legacy_hash;
        execute format('select count(*), md5(string_agg(r::text, '','' order by r::text)) from (select %s from public.%I) r', cast_columns, plan.table_name)
           into v_imported_rows, v_imported_hash;

        if plan.table_name in ('order_settings', 'order_addons', 'management_outflow_categories') then
            -- Seed tables may gain release-specific rows; legacy rows must all be present.
            execute format('select count(*) from (select %s from legacy.%I except select %s from public.%I) missing', cast_columns, plan.table_name, cast_columns, plan.table_name)
               into v_imported_rows;
            v_imported_rows := v_legacy_rows - v_imported_rows;
            v_imported_hash := v_legacy_hash;
        end if;

        insert into import_report values (plan.table_name, v_legacy_rows, v_imported_rows, v_legacy_hash, v_imported_hash);
    end loop;

    insert into import_report
    select 'inventory_costing_policy_packaging_components',
           (select count(*) from legacy.inventory_costing_policy_packaging_components),
           (select count(*) from public.inventory_costing_policy_packaging_components),
           null, null;

    if exists (select 1 from import_report r where r.legacy_rows <> r.imported_rows or r.legacy_hash is distinct from r.imported_hash) then
        raise exception 'import verification failed: %', (
            select string_agg(r.table_name, ', ') from import_report r
            where r.legacy_rows <> r.imported_rows or r.legacy_hash is distinct from r.imported_hash
        );
    end if;
end
$$;

-- Every foreign key must resolve (row triggers and FK checks were skipped during the load).
do $$
declare
    fk record;
    orphans bigint;
begin
    for fk in
        select c.conrelid::regclass as child, a.attname as child_column,
               c.confrelid::regclass as parent, pa.attname as parent_column
        from pg_constraint c
        join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
        join pg_attribute pa on pa.attrelid = c.confrelid and pa.attnum = c.confkey[1]
        join pg_namespace n on n.oid = c.connamespace
        where c.contype = 'f' and n.nspname = 'public' and cardinality(c.conkey) = 1
    loop
        execute format(
            'select count(*) from %s child where child.%I is not null and not exists (select 1 from %s parent where parent.%I = child.%I)',
            fk.child, fk.child_column, fk.parent, fk.parent_column, fk.child_column
        ) into orphans;

        if orphans > 0 then
            raise exception 'orphan rows: %.% -> %.% (% rows)', fk.child, fk.child_column, fk.parent, fk.parent_column, orphans;
        end if;
    end loop;
end
$$;

-- Business reconciliation: money, stock and orders must match exactly.
create temporary table import_reconciliation (
    metric text not null,
    subject text not null,
    legacy_value numeric,
    imported_value numeric
) on commit drop;

insert into import_reconciliation
select 'wallet balance', coalesce(l.wallet_id, i.wallet_id)::text, l.balance, i.balance
from (select wallet_id, sum(amount) as balance from legacy.ledger_entries group by wallet_id) l
full join (select wallet_id, sum(amount) as balance from public.ledger_entries group by wallet_id) i using (wallet_id);

insert into import_reconciliation
select 'stock', format('item %s unit %s', coalesce(l.inventory_item_id, i.inventory_item_id), coalesce(l.unit_id, i.unit_id)), l.quantity, i.quantity
from (select inventory_item_id, unit_id, sum(quantity) as quantity from legacy.inventory_stock_movements group by 1, 2) l
full join (select inventory_item_id, unit_id, sum(quantity) as quantity from public.inventory_stock_movements group by 1, 2) i
  using (inventory_item_id, unit_id);

insert into import_reconciliation
select 'orders total', coalesce(l.status, i.status) || ' / ' || coalesce(l.payment_status, i.payment_status), l.total, i.total
from (select status, payment_status, sum(total) as total from legacy.orders group by 1, 2) l
full join (select status, payment_status, sum(total) as total from public.orders group by 1, 2) i
  using (status, payment_status);

do $$
begin
    if exists (select 1 from import_reconciliation where legacy_value is distinct from imported_value) then
        raise exception 'reconciliation failed: %', (
            select string_agg(metric || ' ' || subject, ', ') from import_reconciliation
            where legacy_value is distinct from imported_value
        );
    end if;
end
$$;

select table_name, legacy_rows, imported_rows
from import_report
where legacy_rows > 0
order by table_name;

select metric, subject, legacy_value, imported_value
from import_reconciliation
order by metric, subject;

drop schema legacy cascade;
drop user mapping for current_user server legacy_source;
drop server legacy_source;
drop extension postgres_fdw;

commit;
