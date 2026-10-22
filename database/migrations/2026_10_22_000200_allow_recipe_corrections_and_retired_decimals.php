<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Controlled recipe maintenance. A formula never used in production may be deleted
 * together with its ingredients, so an accidental save can be corrected without
 * history; a used formula stays immutable. An item referenced only by retired recipes
 * may change its decimals; unit family, base unit and stock tracking stay locked by
 * any recipe reference because retired history renders in the item's base unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared($this->recipeGuard(allowUnusedDeletion: true).$this->itemGuard(activeOnlyPrecision: true).<<<'SQL'
CREATE OR REPLACE FUNCTION inventory_recipe_ingredient_removed() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM inventory_recipe_versions WHERE id=OLD.recipe_version_id) THEN
  RAISE EXCEPTION 'Recipe ingredients are removed only with their unused version' USING ERRCODE='55000';
 END IF; RETURN OLD;
END; $$;
CREATE CONSTRAINT TRIGGER inventory_recipe_ingredient_removed AFTER DELETE ON inventory_recipe_ingredients DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_recipe_ingredient_removed();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS inventory_recipe_ingredient_removed ON inventory_recipe_ingredients; DROP FUNCTION IF EXISTS inventory_recipe_ingredient_removed();'
            .$this->recipeGuard(allowUnusedDeletion: false).$this->itemGuard(activeOnlyPrecision: false));
    }

    /** @return literal-string */
    private function recipeGuard(bool $allowUnusedDeletion): string
    {
        $ingredientDelete = $allowUnusedDeletion ? <<<'SQL'
  IF TG_OP='DELETE' THEN
   PERFORM 1 FROM inventory_recipe_versions WHERE id=OLD.recipe_version_id FOR UPDATE;
   IF EXISTS(SELECT 1 FROM inventory_production_batches WHERE recipe_version_id=OLD.recipe_version_id) THEN
    RAISE EXCEPTION 'Used recipe formulas are immutable' USING ERRCODE='55000';
   END IF;
   RETURN OLD;
  END IF;
SQL : '';
        $versionDelete = $allowUnusedDeletion ? <<<'SQL'
  IF TG_OP='DELETE' THEN
   IF EXISTS(SELECT 1 FROM inventory_production_batches WHERE recipe_version_id=OLD.id) THEN
    RAISE EXCEPTION 'Used recipe formulas are immutable' USING ERRCODE='55000';
   END IF;
   RETURN OLD;
  END IF;
SQL : <<<'SQL'
  IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Recipe versions are immutable' USING ERRCODE='55000'; END IF;
SQL;

        return <<<SQL
CREATE OR REPLACE FUNCTION inventory_recipe_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$
DECLARE v inventory_recipe_versions%ROWTYPE; i inventory_items%ROWTYPE; u inventory_units%ROWTYPE; r inventory_recipes%ROWTYPE;
BEGIN
 IF TG_TABLE_NAME='inventory_recipe_ingredients' THEN
{$ingredientDelete}
  IF TG_OP<>'INSERT' THEN RAISE EXCEPTION 'Recipe ingredients are immutable' USING ERRCODE='55000'; END IF;
  SELECT * INTO v FROM inventory_recipe_versions WHERE id=NEW.recipe_version_id FOR UPDATE;
  IF v.is_sealed OR v.creation_xid IS DISTINCT FROM pg_current_xact_id() THEN
   RAISE EXCEPTION 'Cannot append to sealed recipe history' USING ERRCODE='55000';
  END IF;
  SELECT * INTO i FROM inventory_items WHERE id=NEW.inventory_item_id FOR UPDATE;
  SELECT * INTO u FROM inventory_units WHERE id=NEW.unit_id;
  IF i.id IS NULL OR NOT i.recipe_input_enabled OR NOT i.is_active OR NOT i.tracks_stock
  OR u.family IS DISTINCT FROM i.unit_family OR NEW.inventory_item_id=v.output_inventory_item_id
  OR NEW.base_quantity IS DISTINCT FROM NEW.quantity*u.factor_to_base
  OR NEW.base_quantity<>round(NEW.base_quantity,i.quantity_precision) THEN
   RAISE EXCEPTION 'Invalid eligible recipe ingredient or conversion' USING ERRCODE='23514';
  END IF;
 ELSE
{$versionDelete}
  PERFORM pg_advisory_xact_lock(hashtextextended('inventory:recipe-graph',0));
  SELECT * INTO r FROM inventory_recipes WHERE id=NEW.recipe_id FOR UPDATE;
  SELECT * INTO i FROM inventory_items WHERE id=NEW.output_inventory_item_id FOR UPDATE;
  SELECT * INTO u FROM inventory_units WHERE id=NEW.output_unit_id;
  IF TG_OP='INSERT' THEN
   IF NEW.is_active OR NEW.is_sealed OR NOT r.is_active OR NOT i.production_output_enabled OR NOT i.is_active OR NOT i.tracks_stock
   OR u.family IS DISTINCT FROM i.unit_family OR NEW.recipe_name_snapshot IS DISTINCT FROM r.name
   OR NEW.expected_output_base_quantity IS DISTINCT FROM NEW.expected_output_quantity*u.factor_to_base
   OR NEW.expected_output_base_quantity<>round(NEW.expected_output_base_quantity,i.quantity_precision) THEN
    RAISE EXCEPTION 'Invalid eligible recipe output or conversion' USING ERRCODE='23514';
   END IF;
  ELSE
   IF (to_jsonb(NEW)-ARRAY['is_active','is_sealed','updated_at']) IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['is_active','is_sealed','updated_at'])
   OR NOT ((OLD.is_sealed AND OLD.is_active AND NOT NEW.is_active AND NEW.is_sealed)
           OR (NOT OLD.is_sealed AND NEW.is_sealed AND NEW.is_active AND OLD.creation_xid=pg_current_xact_id())) THEN
    RAISE EXCEPTION 'Recipe formula history is immutable' USING ERRCODE='55000';
   END IF;
   IF NOT OLD.is_sealed THEN
    IF NOT EXISTS(SELECT 1 FROM inventory_recipe_ingredients WHERE recipe_version_id=NEW.id) THEN
     RAISE EXCEPTION 'Recipe needs ingredients before sealing' USING ERRCODE='23514';
    END IF;
    IF EXISTS(WITH RECURSIVE graph(output_id,input_id) AS (
     SELECT rv.output_inventory_item_id, ri.inventory_item_id FROM inventory_recipe_versions rv
     JOIN inventory_recipe_ingredients ri ON ri.recipe_version_id=rv.id WHERE rv.is_active OR rv.id=NEW.id
     UNION SELECT g.output_id, ri.inventory_item_id FROM graph g JOIN inventory_recipe_versions rv
     ON rv.output_inventory_item_id=g.input_id AND (rv.is_active OR rv.id=NEW.id)
     JOIN inventory_recipe_ingredients ri ON ri.recipe_version_id=rv.id)
     SELECT 1 FROM graph WHERE output_id=input_id) THEN
     RAISE EXCEPTION 'Active recipe cycle' USING ERRCODE='23514';
    END IF;
   END IF;
  END IF;
 END IF;
 RETURN NEW;
END; \$\$;

SQL;
    }

    /** @return literal-string */
    private function itemGuard(bool $activeOnlyPrecision): string
    {
        $measurement = $activeOnlyPrecision ? <<<'SQL'
 IF (NEW.base_unit_id IS DISTINCT FROM OLD.base_unit_id OR NEW.unit_family IS DISTINCT FROM OLD.unit_family
 OR NEW.tracks_stock IS DISTINCT FROM OLD.tracks_stock)
 AND (EXISTS(SELECT 1 FROM inventory_recipe_versions WHERE output_inventory_item_id=OLD.id)
 OR EXISTS(SELECT 1 FROM inventory_recipe_ingredients WHERE inventory_item_id=OLD.id)) THEN
  RAISE EXCEPTION 'Recipe measurement identity is immutable' USING ERRCODE='55000';
 END IF;
 IF NEW.quantity_precision IS DISTINCT FROM OLD.quantity_precision
 AND (EXISTS(SELECT 1 FROM inventory_recipe_versions WHERE output_inventory_item_id=OLD.id AND is_active)
 OR EXISTS(SELECT 1 FROM inventory_recipe_ingredients ri JOIN inventory_recipe_versions rv ON rv.id=ri.recipe_version_id WHERE ri.inventory_item_id=OLD.id AND rv.is_active)) THEN
  RAISE EXCEPTION 'Active recipe precision is immutable' USING ERRCODE='55000';
 END IF;
SQL : <<<'SQL'
 IF (NEW.base_unit_id IS DISTINCT FROM OLD.base_unit_id OR NEW.unit_family IS DISTINCT FROM OLD.unit_family
 OR NEW.quantity_precision IS DISTINCT FROM OLD.quantity_precision OR NEW.tracks_stock IS DISTINCT FROM OLD.tracks_stock)
 AND (EXISTS(SELECT 1 FROM inventory_recipe_versions WHERE output_inventory_item_id=OLD.id)
 OR EXISTS(SELECT 1 FROM inventory_recipe_ingredients WHERE inventory_item_id=OLD.id)) THEN
  RAISE EXCEPTION 'Recipe measurement identity is immutable' USING ERRCODE='55000';
 END IF;
SQL;

        return <<<SQL
CREATE OR REPLACE FUNCTION inventory_recipe_item_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$
BEGIN
{$measurement}
 IF (NOT NEW.production_output_enabled AND EXISTS(SELECT 1 FROM inventory_recipe_versions WHERE output_inventory_item_id=OLD.id AND is_active))
 OR (NOT NEW.recipe_input_enabled AND EXISTS(SELECT 1 FROM inventory_recipe_ingredients ri JOIN inventory_recipe_versions rv ON rv.id=ri.recipe_version_id WHERE ri.inventory_item_id=OLD.id AND rv.is_active)) THEN
  RAISE EXCEPTION 'Active recipe capabilities are required' USING ERRCODE='23514';
 END IF; RETURN NEW;
END; \$\$;

SQL;
    }
};
