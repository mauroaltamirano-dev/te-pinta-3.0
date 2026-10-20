<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dine-in table service: one open session per dining table, backed by one POS order.
 * Rounds and their lines are immutable facts of what was sent to the kitchen; corrections
 * are void rounds. Additive only: no existing row is read or rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create table table_sessions (
                id bigserial primary key,
                restaurant_id bigint not null
                    default nullif(current_setting('app.current_restaurant_id', true), '')::bigint
                    constraint table_sessions_restaurant_id_foreign references restaurants (id) on delete restrict,
                dining_table_id bigint not null
                    constraint table_sessions_dining_table_id_foreign references dining_tables (id) on delete restrict,
                order_id bigint not null
                    constraint table_sessions_order_id_foreign references orders (id) on delete restrict,
                waiter_id bigint not null
                    constraint table_sessions_waiter_id_foreign references users (id) on delete restrict,
                status varchar(12) not null default 'open',
                opened_at timestamp(0) without time zone not null,
                bill_requested_at timestamp(0) without time zone null,
                bill_requested_by bigint null
                    constraint table_sessions_bill_requested_by_foreign references users (id) on delete restrict,
                closed_at timestamp(0) without time zone null,
                closed_by bigint null
                    constraint table_sessions_closed_by_foreign references users (id) on delete restrict,
                created_at timestamp(0) without time zone null,
                updated_at timestamp(0) without time zone null,
                constraint table_sessions_order_id_unique unique (order_id),
                constraint table_sessions_status_valid check (status in ('open', 'closed', 'cancelled')),
                constraint table_sessions_closure_valid check (
                    (status = 'open' and closed_at is null and closed_by is null)
                    or (status <> 'open' and closed_at is not null and closed_by is not null)
                ),
                constraint table_sessions_bill_request_valid check ((bill_requested_at is null) = (bill_requested_by is null))
            );
            create index table_sessions_restaurant_id_index on table_sessions (restaurant_id);
            create unique index table_sessions_one_open_per_table on table_sessions (dining_table_id) where status = 'open';
            create index table_sessions_status_index on table_sessions (status, dining_table_id);

            create table table_rounds (
                id bigserial primary key,
                restaurant_id bigint not null
                    default nullif(current_setting('app.current_restaurant_id', true), '')::bigint
                    constraint table_rounds_restaurant_id_foreign references restaurants (id) on delete restrict,
                table_session_id bigint not null
                    constraint table_rounds_table_session_id_foreign references table_sessions (id) on delete restrict,
                sequence smallint not null,
                kind varchar(8) not null,
                note varchar(300) null,
                confirmed_by bigint not null
                    constraint table_rounds_confirmed_by_foreign references users (id) on delete restrict,
                confirmed_by_role varchar(16) not null,
                confirmed_at timestamp(0) without time zone not null,
                idempotency_hash char(64) not null,
                fingerprint char(64) not null,
                constraint table_rounds_session_sequence_unique unique (table_session_id, sequence),
                constraint table_rounds_idempotency_unique unique (restaurant_id, idempotency_hash),
                constraint table_rounds_kind_valid check (kind in ('order', 'void')),
                constraint table_rounds_sequence_positive check (sequence > 0),
                constraint table_rounds_role_valid check (confirmed_by_role in ('owner', 'manager', 'cashier', 'waiter', 'kitchen', 'accountant'))
            );
            create index table_rounds_restaurant_id_index on table_rounds (restaurant_id);

            create table table_round_items (
                id bigserial primary key,
                restaurant_id bigint not null
                    default nullif(current_setting('app.current_restaurant_id', true), '')::bigint
                    constraint table_round_items_restaurant_id_foreign references restaurants (id) on delete restrict,
                table_round_id bigint not null
                    constraint table_round_items_table_round_id_foreign references table_rounds (id) on delete restrict,
                product_id bigint null
                    constraint table_round_items_product_id_foreign references products (id) on delete restrict,
                order_addon_id bigint null
                    constraint table_round_items_order_addon_id_foreign references order_addons (id) on delete restrict,
                name varchar(255) not null,
                quantity integer not null,
                constraint table_round_items_target_valid check ((product_id is null) <> (order_addon_id is null)),
                constraint table_round_items_quantity_valid check (quantity between 1 and 120),
                constraint table_round_items_name_valid check (length(btrim(name)) > 0)
            );
            create index table_round_items_restaurant_id_index on table_round_items (restaurant_id);
            create unique index table_round_items_round_product_unique on table_round_items (table_round_id, product_id) where product_id is not null;
            create unique index table_round_items_round_addon_unique on table_round_items (table_round_id, order_addon_id) where order_addon_id is not null;
            create index table_round_items_product_id_index on table_round_items (product_id);
            create index table_round_items_order_addon_id_index on table_round_items (order_addon_id);

            create or replace function table_service_reject_history_mutation() returns trigger language plpgsql as $$
            begin
                raise exception '% rows are immutable; record a void round instead', TG_TABLE_NAME using errcode = '55000';
            end $$;
            create trigger table_rounds_immutable before update or delete on table_rounds
                for each row execute function table_service_reject_history_mutation();
            create trigger table_round_items_immutable before update or delete on table_round_items
                for each row execute function table_service_reject_history_mutation();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('table_sessions')->exists()) {
            throw new RuntimeException('Table service history exists; migrate forward instead of rolling back.');
        }
        DB::unprepared(<<<'SQL'
            drop table table_round_items;
            drop table table_rounds;
            drop table table_sessions;
            drop function table_service_reject_history_mutation();
            SQL);
    }
};
