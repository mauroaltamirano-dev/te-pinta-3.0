<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Server-side print queue for the shop print station. One job per business event (dedupe key);
 * the payload is frozen so a reprint is identical. A claimed job is never claimed again
 * automatically: a person confirms or releases it, so nothing prints twice by accident.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create table print_jobs (
                id bigserial primary key,
                restaurant_id bigint not null
                    default nullif(current_setting('app.current_restaurant_id', true), '')::bigint
                    constraint print_jobs_restaurant_id_foreign references restaurants (id) on delete restrict,
                kind varchar(16) not null,
                order_id bigint not null
                    constraint print_jobs_order_id_foreign references orders (id) on delete restrict,
                dedupe_key varchar(120) not null,
                payload jsonb not null,
                status varchar(10) not null default 'pending',
                attempts smallint not null default 0,
                claim_token uuid null,
                claimed_at timestamp(0) without time zone null,
                claimed_by bigint null
                    constraint print_jobs_claimed_by_foreign references users (id) on delete restrict,
                printed_at timestamp(0) without time zone null,
                printed_by bigint null
                    constraint print_jobs_printed_by_foreign references users (id) on delete restrict,
                reprint_of_id bigint null
                    constraint print_jobs_reprint_of_id_foreign references print_jobs (id) on delete restrict,
                requested_by bigint not null
                    constraint print_jobs_requested_by_foreign references users (id) on delete restrict,
                created_at timestamp(0) without time zone null,
                updated_at timestamp(0) without time zone null,
                constraint print_jobs_dedupe_key_unique unique (restaurant_id, dedupe_key),
                constraint print_jobs_kind_valid check (kind in ('kitchen_ticket', 'bill')),
                constraint print_jobs_status_valid check (status in ('pending', 'claimed', 'printed')),
                constraint print_jobs_payload_object check (jsonb_typeof(payload) = 'object'),
                constraint print_jobs_attempts_valid check (attempts >= 0),
                constraint print_jobs_state_valid check (
                    (status = 'pending' and claim_token is null and printed_at is null)
                    or (status = 'claimed' and claim_token is not null and claimed_at is not null and claimed_by is not null and printed_at is null)
                    or (status = 'printed' and printed_at is not null and printed_by is not null)
                )
            );
            create index print_jobs_restaurant_id_index on print_jobs (restaurant_id);
            create index print_jobs_status_index on print_jobs (status, id);
            create index print_jobs_order_id_index on print_jobs (order_id);
            create index print_jobs_reprint_of_id_index on print_jobs (reprint_of_id) where reprint_of_id is not null;

            create or replace function print_jobs_reject_content_change() returns trigger language plpgsql as $$
            begin
                if TG_OP = 'DELETE' then
                    raise exception 'print_jobs rows are kept as history' using errcode = '55000';
                end if;
                if (NEW.kind, NEW.order_id, NEW.dedupe_key, NEW.payload, NEW.reprint_of_id, NEW.requested_by, NEW.restaurant_id)
                    is distinct from (OLD.kind, OLD.order_id, OLD.dedupe_key, OLD.payload, OLD.reprint_of_id, OLD.requested_by, OLD.restaurant_id) then
                    raise exception 'print_jobs content is immutable; request a reprint instead' using errcode = '55000';
                end if;
                if OLD.status = 'printed' and NEW.status <> 'printed' then
                    raise exception 'a printed job cannot return to the queue; request a reprint instead' using errcode = '55000';
                end if;
                return NEW;
            end $$;
            create trigger print_jobs_content_immutable before update or delete on print_jobs
                for each row execute function print_jobs_reject_content_change();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('print_jobs')->exists()) {
            throw new RuntimeException('Print history exists; migrate forward instead of rolling back.');
        }
        DB::unprepared('drop table print_jobs; drop function print_jobs_reject_content_change();');
    }
};
