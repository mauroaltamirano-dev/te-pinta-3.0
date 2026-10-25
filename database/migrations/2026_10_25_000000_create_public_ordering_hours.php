<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Weekly hours in which the public site takes orders: one window per ISO weekday (1 = Monday),
 * both times null when that day is closed. A closing time at or before the opening time ends the
 * next day (equal times: all day). Additive only; seeded every day 19:00–00:00 for every restaurant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create table public_ordering_hours (
                id bigserial primary key,
                restaurant_id bigint not null
                    default nullif(current_setting('app.current_restaurant_id', true), '')::bigint
                    constraint public_ordering_hours_restaurant_id_foreign references restaurants (id) on delete restrict,
                weekday smallint not null,
                opens_at time(0) without time zone null,
                closes_at time(0) without time zone null,
                created_at timestamp(0) without time zone null,
                updated_at timestamp(0) without time zone null,
                constraint public_ordering_hours_weekday_valid check (weekday between 1 and 7),
                constraint public_ordering_hours_both_or_none check ((opens_at is null) = (closes_at is null)),
                constraint public_ordering_hours_weekday_unique unique (restaurant_id, weekday)
            );
            create index public_ordering_hours_restaurant_id_index on public_ordering_hours (restaurant_id);

            insert into public_ordering_hours (restaurant_id, weekday, opens_at, closes_at, created_at, updated_at)
            select restaurants.id, day, '19:00', '00:00', now(), now()
            from restaurants cross join generate_series(1, 7) as day;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop table public_ordering_hours');
    }
};
