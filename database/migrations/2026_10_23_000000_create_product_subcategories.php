<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Optional product subcategories, used only to filter the catalog. Additive only: existing
 * categories and products are not rewritten; every product starts without a subcategory.
 * The composite foreign key keeps a product's subcategory inside the product's own category.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create table product_subcategories (
                id bigserial primary key,
                restaurant_id bigint not null
                    default nullif(current_setting('app.current_restaurant_id', true), '')::bigint
                    constraint product_subcategories_restaurant_id_foreign references restaurants (id) on delete restrict,
                category_id bigint not null
                    constraint product_subcategories_category_id_foreign references categories (id) on delete restrict,
                name varchar(100) not null,
                slug varchar(255) not null,
                sort_order integer not null default 0,
                is_active boolean not null default true,
                created_at timestamp(0) without time zone null,
                updated_at timestamp(0) without time zone null,
                constraint product_subcategories_sort_order_nonnegative check (sort_order >= 0),
                constraint product_subcategories_id_category_unique unique (id, category_id),
                constraint product_subcategories_slug_unique unique (restaurant_id, category_id, slug)
            );
            create index product_subcategories_restaurant_id_index on product_subcategories (restaurant_id);
            create unique index product_subcategories_name_unique on product_subcategories (category_id, lower(name));

            alter table products add column subcategory_id bigint null;
            alter table products add constraint products_subcategory_matches_category
                foreign key (subcategory_id, category_id) references product_subcategories (id, category_id) on delete restrict;
            create index products_subcategory_id_index on products (subcategory_id);
            SQL);
    }

    public function down(): void
    {
        if (DB::table('product_subcategories')->exists()) {
            throw new RuntimeException('Refusing to roll back: product subcategories exist and their product assignments would be lost. Migrate forward instead.');
        }

        DB::unprepared(<<<'SQL'
            alter table products drop constraint products_subcategory_matches_category;
            alter table products drop column subcategory_id;
            drop table product_subcategories;
            SQL);
    }
};
