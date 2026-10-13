<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_costing_policies', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 160);
            $t->smallInteger('overhead_basis_points');
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
        });
        Schema::create('inventory_costing_policy_presentations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('costing_policy_id')->constrained('inventory_costing_policies')->cascadeOnDelete();
            $t->smallInteger('commercial_quantity');
            $t->string('packaging_mode', 20);
            $t->timestampsTz();
            $t->unique(['costing_policy_id', 'commercial_quantity'], 'costing_presentation_unique');
        });
        Schema::create('inventory_costing_policy_packaging_components', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('presentation_id')->constrained('inventory_costing_policy_presentations')->cascadeOnDelete();
            $t->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $t->decimal('quantity', 20, 6);
            $t->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $t->decimal('base_quantity', 20, 6);
            $t->timestampsTz();
            $t->unique(['presentation_id', 'inventory_item_id'], 'costing_packaging_unique');
        });
        Schema::create('inventory_product_costing_policies', function (Blueprint $t): void {
            $t->foreignId('product_id')->primary()->constrained('products')->restrictOnDelete();
            $t->foreignId('costing_policy_id')->constrained('inventory_costing_policies')->restrictOnDelete();
            $t->timestampsTz();
            $t->index('costing_policy_id');
        });
        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX costing_policy_name_unique ON inventory_costing_policies (lower(name));
ALTER TABLE inventory_costing_policies ADD CONSTRAINT costing_policy_name_valid CHECK (name = btrim(name) AND name <> ''), ADD CONSTRAINT costing_overhead_range CHECK (overhead_basis_points BETWEEN 0 AND 10000);
ALTER TABLE inventory_costing_policy_presentations ADD CONSTRAINT costing_presentation_valid CHECK (commercial_quantity BETWEEN 1 AND 120 AND packaging_mode IN ('none','components'));
ALTER TABLE inventory_costing_policy_packaging_components ADD CONSTRAINT costing_quantity_valid CHECK (quantity > 0 AND quantity < 100000000000000 AND base_quantity > 0 AND base_quantity < 100000000000000);
CREATE OR REPLACE FUNCTION validate_current_costing_component() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item inventory_items%ROWTYPE; u inventory_units%ROWTYPE;
BEGIN
    PERFORM id FROM inventory_costing_policy_presentations WHERE id = NEW.presentation_id FOR UPDATE;
    SELECT * INTO item FROM inventory_items WHERE id = NEW.inventory_item_id FOR SHARE;
    SELECT * INTO u FROM inventory_units WHERE id = NEW.unit_id;
    IF item.id IS NULL OR u.id IS NULL THEN RETURN NEW; END IF;
    IF NOT item.is_active OR NOT item.tracks_stock OR item.kind IS NULL OR item.kind NOT IN ('packaging','consumable') OR item.unit_family <> u.family OR NEW.base_quantity <> NEW.quantity * u.factor_to_base OR NEW.base_quantity <> trunc(NEW.base_quantity, item.quantity_precision) THEN
        RAISE EXCEPTION 'Invalid current packaging item or exact unit conversion' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER current_costing_component_valid BEFORE INSERT OR UPDATE ON inventory_costing_policy_packaging_components FOR EACH ROW EXECUTE FUNCTION validate_current_costing_component();
CREATE OR REPLACE FUNCTION validate_current_costing_presentation() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE ids bigint[]; pid bigint; mode text; has_components boolean;
BEGIN
    IF TG_TABLE_NAME = 'inventory_costing_policy_presentations' THEN
        ids := ARRAY[CASE WHEN TG_OP <> 'INSERT' THEN OLD.id END, CASE WHEN TG_OP <> 'DELETE' THEN NEW.id END];
    ELSE
        ids := ARRAY[CASE WHEN TG_OP <> 'INSERT' THEN OLD.presentation_id END, CASE WHEN TG_OP <> 'DELETE' THEN NEW.presentation_id END];
    END IF;
    FOREACH pid IN ARRAY ids LOOP
        SELECT packaging_mode INTO mode FROM inventory_costing_policy_presentations WHERE id = pid FOR UPDATE;
        IF FOUND THEN
            SELECT EXISTS(SELECT 1 FROM inventory_costing_policy_packaging_components WHERE presentation_id = pid) INTO has_components;
            IF (mode = 'components') <> has_components THEN
                RAISE EXCEPTION 'Packaging requires explicit none or nonempty components' USING ERRCODE = '23514';
            END IF;
        END IF;
    END LOOP;
    RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER current_costing_presentation_complete AFTER INSERT OR UPDATE ON inventory_costing_policy_presentations DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION validate_current_costing_presentation();
CREATE CONSTRAINT TRIGGER current_costing_components_complete AFTER INSERT OR UPDATE OR DELETE ON inventory_costing_policy_packaging_components DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION validate_current_costing_presentation();
SQL);
    }

    public function down(): void
    {
        if (DB::table('inventory_costing_policies')->exists()) {
            throw new RuntimeException('Refusing populated current costing rollback; preserve policies and assignments.');
        }
        Schema::drop('inventory_product_costing_policies');
        Schema::drop('inventory_costing_policy_packaging_components');
        Schema::drop('inventory_costing_policy_presentations');
        Schema::drop('inventory_costing_policies');
        DB::statement('DROP FUNCTION validate_current_costing_component()');
        DB::statement('DROP FUNCTION validate_current_costing_presentation()');
    }
};
