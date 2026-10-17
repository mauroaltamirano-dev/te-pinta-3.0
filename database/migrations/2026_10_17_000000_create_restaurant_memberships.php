<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff access becomes a per-restaurant membership with a code-defined role
 * (App\Modules\Identity\Enums\Role). `users.is_admin` and `users.staff_profile`
 * are converted and kept for one release as a rollback path; no code reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create table restaurant_user (
                id bigserial primary key,
                restaurant_id bigint not null
                    default nullif(current_setting('app.current_restaurant_id', true), '')::bigint
                    constraint restaurant_user_restaurant_id_foreign references restaurants (id) on delete restrict,
                user_id bigint not null
                    constraint restaurant_user_user_id_foreign references users (id) on delete cascade,
                role varchar(16) not null
                    constraint restaurant_user_role_valid check (role in ('owner', 'manager', 'cashier', 'waiter', 'kitchen', 'accountant')),
                is_active boolean not null default true,
                created_at timestamp(0) without time zone null,
                updated_at timestamp(0) without time zone null,
                constraint restaurant_user_restaurant_id_user_id_unique unique (restaurant_id, user_id)
            )
            SQL);
        DB::statement('create index restaurant_user_user_id_index on restaurant_user (user_id)');

        // Existing staff belongs to the only restaurant; administrators win over a staff profile.
        DB::statement(<<<'SQL'
            insert into restaurant_user (restaurant_id, user_id, role, is_active, created_at, updated_at)
            select 1, id, case when is_admin then 'owner' else staff_profile end, true, now(), now()
            from users
            where is_admin or staff_profile is not null
            order by id
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_user');
    }
};
