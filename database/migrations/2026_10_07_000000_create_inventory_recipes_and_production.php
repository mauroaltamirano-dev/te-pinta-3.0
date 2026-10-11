<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_recipes', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 160);
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('inventory_recipe_versions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('recipe_id')->constrained('inventory_recipes')->restrictOnDelete();
            $t->integer('version');
            $t->string('recipe_name_snapshot', 160);
            $t->foreignId('output_inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $t->decimal('expected_output_quantity', 20, 6);
            $t->foreignId('output_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $t->decimal('expected_output_base_quantity', 20, 6);
            $t->boolean('is_active')->default(false);
            $t->boolean('is_sealed')->default(false);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['recipe_id', 'version']);
        });
        Schema::create('inventory_recipe_ingredients', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('recipe_version_id')->constrained('inventory_recipe_versions')->restrictOnDelete();
            $t->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $t->decimal('quantity', 20, 6);
            $t->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $t->decimal('base_quantity', 20, 6);
            $t->timestampsTz();
            $t->unique(['recipe_version_id', 'inventory_item_id']);
        });
        Schema::create('inventory_production_batches', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('recipe_version_id')->constrained('inventory_recipe_versions')->restrictOnDelete();
            $t->foreignId('output_inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $t->decimal('expected_output_base_quantity', 20, 6);
            $t->decimal('actual_output_quantity', 20, 6);
            $t->foreignId('output_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $t->decimal('actual_output_base_quantity', 20, 6);
            $t->date('effective_date');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('notes', 240)->nullable();
            $t->foreignId('inventory_transaction_id')->nullable()->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $t->bigInteger('total_cost')->nullable();
            $t->string('cost_coverage', 16)->default('unknown');
            $t->jsonb('operational_warnings')->default('[]');
            $t->uuid('idempotency_key')->unique();
            $t->string('fingerprint', 64);
            $t->timestampsTz();
        });
        Schema::create('inventory_production_cost_allocations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('production_batch_id')->constrained('inventory_production_batches')->restrictOnDelete();
            $t->foreignId('stock_movement_id')->unique()->constrained('inventory_stock_movements')->restrictOnDelete();
            $t->foreignId('inventory_cost_layer_id')->nullable()->unique()->constrained('inventory_cost_layers')->restrictOnDelete();
            $t->decimal('basis_quantity', 20, 6);
            $t->bigInteger('basis_total_cost');
            $t->decimal('allocated_quantity', 20, 6);
            $t->bigInteger('allocated_cost')->nullable();
            $t->string('method', 32);
            $t->string('fallback', 32)->nullable();
            $t->foreignId('reversal_of_id')->nullable()->unique()->constrained('inventory_production_cost_allocations')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('inventory_production_reversals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('production_batch_id')->unique()->constrained('inventory_production_batches')->restrictOnDelete();
            $t->foreignId('inventory_transaction_id')->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $t->date('effective_date');
            $t->string('reason', 240);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        foreach (['inventory_recipe_versions', 'inventory_production_batches', 'inventory_production_reversals'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN creation_xid xid8 NOT NULL DEFAULT pg_current_xact_id()");
        }
        DB::statement('CREATE UNIQUE INDEX inventory_recipe_name_unique ON inventory_recipes(lower(btrim(name)))');
        DB::statement("ALTER TABLE inventory_recipes ADD CONSTRAINT inventory_recipe_name_valid CHECK(btrim(name)<>'')");
        DB::statement('CREATE UNIQUE INDEX inventory_recipe_one_current ON inventory_recipe_versions(recipe_id) WHERE is_active');
        DB::statement('ALTER TABLE inventory_recipe_versions ADD CONSTRAINT inventory_recipe_output_valid CHECK(version>0 AND expected_output_quantity>0 AND expected_output_base_quantity>0 AND (NOT is_active OR is_sealed))');
        DB::statement('ALTER TABLE inventory_recipe_ingredients ADD CONSTRAINT inventory_recipe_input_positive CHECK(quantity>0 AND base_quantity>0)');
        DB::statement("ALTER TABLE inventory_production_batches ADD CONSTRAINT inventory_production_values_valid CHECK(actual_output_quantity>0 AND actual_output_base_quantity>0 AND expected_output_base_quantity>0 AND (total_cost IS NULL OR total_cost>=0) AND cost_coverage IN ('complete','unreliable','partial','unknown') AND (total_cost IS NOT NULL)=(cost_coverage IN ('complete','unreliable')) AND fingerprint ~ '^[0-9a-f]{64}$')");
        Schema::table('inventory_cost_layers', function (Blueprint $t): void {
            $t->string('valuation_warning', 32)->nullable();
        });
        DB::statement('ALTER TABLE inventory_cost_layers DROP CONSTRAINT inventory_cost_layers_reversal_shape_valid');
        DB::statement('ALTER TABLE inventory_cost_layers ADD CONSTRAINT inventory_cost_layers_reversal_shape_valid CHECK(quantity<>0 AND ((quantity>0 AND total_cost>=0) OR (quantity<0 AND total_cost<=0)))');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION inventory_validate_cost_layer() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE m inventory_stock_movements%ROWTYPE; t inventory_transactions%ROWTYPE; o inventory_cost_layers%ROWTYPE;
BEGIN
 SELECT * INTO m FROM inventory_stock_movements WHERE id=NEW.stock_movement_id;
 SELECT * INTO t FROM inventory_transactions WHERE id=NEW.inventory_transaction_id;
 IF m.id IS NULL OR t.id IS NULL OR NEW.inventory_item_id IS DISTINCT FROM m.inventory_item_id
 OR NEW.inventory_transaction_id IS DISTINCT FROM m.inventory_transaction_id OR NEW.quantity IS DISTINCT FROM m.quantity
 OR NEW.effective_date IS DISTINCT FROM t.effective_date THEN
  RAISE EXCEPTION 'Cost layer must exactly match its source movement' USING ERRCODE='23514';
 END IF;
 IF NEW.reversal_of_id IS NULL THEN
  IF t.type='reversal' OR (NEW.quantity<0 AND t.type<>'production') THEN
   RAISE EXCEPTION 'Only production may create original signed consumption layers' USING ERRCODE='23514';
  END IF;
 ELSE
  SELECT * INTO o FROM inventory_cost_layers WHERE id=NEW.reversal_of_id;
  IF o.id IS NULL OR o.reversal_of_id IS NOT NULL OR t.type<>'reversal'
  OR t.reversal_of_id IS DISTINCT FROM o.inventory_transaction_id OR NEW.inventory_item_id IS DISTINCT FROM o.inventory_item_id
  OR NEW.quantity IS DISTINCT FROM -o.quantity OR NEW.total_cost IS DISTINCT FROM -o.total_cost
  OR NEW.valuation_warning IS DISTINCT FROM o.valuation_warning THEN
   RAISE EXCEPTION 'Cost reversal must exactly compensate the original transaction layer' USING ERRCODE='23514';
  END IF;
 END IF;
 RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION inventory_recipe_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE v inventory_recipe_versions%ROWTYPE; i inventory_items%ROWTYPE; u inventory_units%ROWTYPE; r inventory_recipes%ROWTYPE;
BEGIN
 IF TG_TABLE_NAME='inventory_recipe_ingredients' THEN
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
  IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Recipe versions are immutable' USING ERRCODE='55000'; END IF;
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
END; $$;
CREATE OR REPLACE FUNCTION inventory_recipe_sealed_at_commit() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NOT EXISTS(SELECT 1 FROM inventory_recipe_versions WHERE id=NEW.id AND is_sealed) THEN
  RAISE EXCEPTION 'Recipe version must be sealed atomically' USING ERRCODE='23514';
 END IF; RETURN NEW;
END; $$;
CREATE OR REPLACE FUNCTION inventory_recipe_item_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF (NEW.base_unit_id IS DISTINCT FROM OLD.base_unit_id OR NEW.unit_family IS DISTINCT FROM OLD.unit_family
 OR NEW.quantity_precision IS DISTINCT FROM OLD.quantity_precision OR NEW.tracks_stock IS DISTINCT FROM OLD.tracks_stock)
 AND (EXISTS(SELECT 1 FROM inventory_recipe_versions WHERE output_inventory_item_id=OLD.id)
 OR EXISTS(SELECT 1 FROM inventory_recipe_ingredients WHERE inventory_item_id=OLD.id)) THEN
  RAISE EXCEPTION 'Recipe measurement identity is immutable' USING ERRCODE='55000';
 END IF;
 IF (NOT NEW.production_output_enabled AND EXISTS(SELECT 1 FROM inventory_recipe_versions WHERE output_inventory_item_id=OLD.id AND is_active))
 OR (NOT NEW.recipe_input_enabled AND EXISTS(SELECT 1 FROM inventory_recipe_ingredients ri JOIN inventory_recipe_versions rv ON rv.id=ri.recipe_version_id WHERE ri.inventory_item_id=OLD.id AND rv.is_active)) THEN
  RAISE EXCEPTION 'Active recipe capabilities are required' USING ERRCODE='23514';
 END IF; RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION inventory_production_insert_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE v inventory_recipe_versions%ROWTYPE; r inventory_recipes%ROWTYPE;
BEGIN
 SELECT recipe.* INTO r FROM inventory_recipes recipe JOIN inventory_recipe_versions version ON version.recipe_id=recipe.id WHERE version.id=NEW.recipe_version_id FOR UPDATE OF recipe;
 SELECT * INTO v FROM inventory_recipe_versions WHERE id=NEW.recipe_version_id FOR UPDATE;
 IF v.id IS NULL OR NOT v.is_active OR NOT v.is_sealed OR NOT r.is_active THEN
  RAISE EXCEPTION 'Production needs a current recipe' USING ERRCODE='23514';
 END IF;
 PERFORM id FROM inventory_items WHERE id=v.output_inventory_item_id OR id IN (SELECT inventory_item_id FROM inventory_recipe_ingredients WHERE recipe_version_id=v.id) ORDER BY id FOR UPDATE;
 IF NEW.inventory_transaction_id IS NOT NULL OR NEW.total_cost IS NOT NULL OR NEW.cost_coverage<>'unknown'
 OR NEW.output_inventory_item_id IS DISTINCT FROM v.output_inventory_item_id
 OR NEW.expected_output_base_quantity IS DISTINCT FROM v.expected_output_base_quantity
 OR EXISTS(SELECT 1 FROM inventory_items WHERE (id=v.output_inventory_item_id OR id IN (SELECT inventory_item_id FROM inventory_recipe_ingredients WHERE recipe_version_id=v.id)) AND (NOT is_active OR NOT tracks_stock OR (id=v.output_inventory_item_id AND NOT production_output_enabled) OR (id<>v.output_inventory_item_id AND NOT recipe_input_enabled))) THEN
  RAISE EXCEPTION 'Invalid production initial snapshot or capabilities' USING ERRCODE='23514';
 END IF; RETURN NEW;
END; $$;

-- Validation of the common allocator's recorded basis, not another costing service.
CREATE OR REPLACE FUNCTION inventory_production_basis_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE b inventory_production_batches%ROWTYPE; m inventory_stock_movements%ROWTYPE; q numeric; c bigint; stock numeric; latest_q numeric; latest_c bigint; expected_q numeric; expected_c bigint; expected_cost bigint; expected_fallback text; warned numeric;
BEGIN
 IF NEW.reversal_of_id IS NOT NULL THEN RETURN NEW; END IF;
 SELECT * INTO b FROM inventory_production_batches WHERE id=NEW.production_batch_id;
 SELECT * INTO m FROM inventory_stock_movements WHERE id=NEW.stock_movement_id;
 SELECT COALESCE(sum(quantity),0),COALESCE(sum(total_cost),0) INTO q,c FROM inventory_cost_layers WHERE inventory_item_id=m.inventory_item_id AND inventory_transaction_id<>m.inventory_transaction_id;
 SELECT COALESCE(sum(quantity),0) INTO stock FROM inventory_stock_movements WHERE inventory_item_id=m.inventory_item_id AND inventory_transaction_id<>m.inventory_transaction_id;
 SELECT quantity,total_cost INTO latest_q,latest_c FROM inventory_cost_layers WHERE inventory_item_id=m.inventory_item_id AND inventory_transaction_id<>m.inventory_transaction_id AND quantity>0 AND total_cost>0 ORDER BY effective_date DESC,id DESC LIMIT 1;
 IF q>0 AND c>=0 THEN
  expected_q=q; expected_c=c;
  IF NEW.allocated_quantity<=q THEN expected_cost=round(c*NEW.allocated_quantity/q);
  ELSE expected_cost=c+COALESCE(round(latest_c*(NEW.allocated_quantity-q)/latest_q),0); expected_fallback='negative_stock_latest_basis'; END IF;
 ELSE expected_q=COALESCE(latest_q,0); expected_c=COALESCE(latest_c,0); expected_cost=round(latest_c*NEW.allocated_quantity/latest_q);
  expected_fallback=CASE WHEN latest_q IS NULL THEN 'no_cost_history' ELSE 'negative_stock_latest_basis' END;
 END IF;
 IF stock>0 AND stock<>q THEN expected_cost=NULL; expected_fallback='partial_valuation'; END IF;
 SELECT COALESCE(sum(quantity),0) INTO warned FROM inventory_cost_layers WHERE inventory_item_id=m.inventory_item_id AND inventory_transaction_id<>m.inventory_transaction_id AND valuation_warning IS NOT NULL;
 IF expected_fallback IS NULL AND warned>0 THEN expected_fallback='unreliable_input'; END IF;
 IF expected_fallback IS NULL AND expected_cost IS NOT NULL AND stock<NEW.allocated_quantity THEN expected_fallback='insufficient_stock'; END IF;
 IF NEW.fallback IS DISTINCT FROM expected_fallback THEN
  RAISE EXCEPTION 'Production allocator fallback must retain valuation warning' USING ERRCODE='23514';
 END IF;
 IF NEW.basis_quantity IS DISTINCT FROM expected_q OR NEW.basis_total_cost IS DISTINCT FROM expected_c OR NEW.allocated_cost IS DISTINCT FROM expected_cost
 OR NEW.allocated_quantity IS DISTINCT FROM -m.quantity THEN
  RAISE EXCEPTION 'Production allocator basis must match locked ledger history' USING ERRCODE='23514';
 END IF; RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION inventory_production_history_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='UPDATE' AND TG_TABLE_NAME='inventory_production_batches' AND OLD.inventory_transaction_id IS NULL
 AND NEW.inventory_transaction_id IS NOT NULL AND OLD.creation_xid=pg_current_xact_id()
 AND (to_jsonb(NEW)-ARRAY['inventory_transaction_id','total_cost','cost_coverage','operational_warnings','updated_at'])
 IS NOT DISTINCT FROM (to_jsonb(OLD)-ARRAY['inventory_transaction_id','total_cost','cost_coverage','operational_warnings','updated_at']) THEN
  RETURN NEW;
 END IF;
 RAISE EXCEPTION 'Production history is immutable' USING ERRCODE='55000';
END; $$;
CREATE OR REPLACE FUNCTION inventory_production_append_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE b inventory_production_batches%ROWTYPE; r inventory_production_reversals%ROWTYPE; t inventory_transactions%ROWTYPE;
BEGIN
 IF TG_TABLE_NAME='inventory_production_cost_allocations' THEN
  SELECT * INTO b FROM inventory_production_batches WHERE id=NEW.production_batch_id FOR UPDATE;
  IF NEW.reversal_of_id IS NOT NULL THEN SELECT * INTO r FROM inventory_production_reversals WHERE production_batch_id=b.id; END IF;
 ELSE
  SELECT * INTO t FROM inventory_transactions WHERE id=NEW.inventory_transaction_id;
  IF t.type='production' THEN SELECT * INTO b FROM inventory_production_batches WHERE id::text=t.source_id FOR UPDATE;
  ELSIF t.type='reversal' THEN
   SELECT * INTO r FROM inventory_production_reversals WHERE inventory_transaction_id=t.id;
   SELECT * INTO b FROM inventory_production_batches WHERE inventory_transaction_id=t.reversal_of_id FOR UPDATE;
  END IF;
 END IF;
 IF b.id IS NOT NULL AND ((r.id IS NULL AND b.creation_xid IS DISTINCT FROM pg_current_xact_id())
 OR (r.id IS NOT NULL AND r.creation_xid IS DISTINCT FROM pg_current_xact_id())) THEN
  RAISE EXCEPTION 'Cannot append to committed production history' USING ERRCODE='55000';
 END IF; RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION inventory_production_reconcile() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE b inventory_production_batches%ROWTYPE; v inventory_recipe_versions%ROWTYPE; t inventory_transactions%ROWTYPE; u inventory_units%ROWTYPE; r inventory_production_reversals%ROWTYPE; o inventory_transactions%ROWTYPE;
DECLARE n bigint; known bigint; subtotal bigint;
BEGIN
 IF TG_TABLE_NAME='inventory_transactions' THEN
  IF NEW.type='production' THEN SELECT * INTO b FROM inventory_production_batches WHERE inventory_transaction_id=NEW.id;
   IF b.id IS NULL THEN RAISE EXCEPTION 'Production requires its exact batch source' USING ERRCODE='23514'; END IF;
  ELSIF NEW.type='reversal' THEN
   SELECT * INTO o FROM inventory_transactions WHERE id=NEW.reversal_of_id;
   IF o.type<>'production' THEN RETURN NEW; END IF;
   SELECT * INTO r FROM inventory_production_reversals WHERE inventory_transaction_id=NEW.id;
   IF r.id IS NULL THEN RAISE EXCEPTION 'Production reversal requires its source' USING ERRCODE='23514'; END IF;
   SELECT * INTO b FROM inventory_production_batches WHERE id=r.production_batch_id;
  ELSE RETURN NEW; END IF;
 ELSIF TG_TABLE_NAME='inventory_production_reversals' THEN
  SELECT * INTO r FROM inventory_production_reversals WHERE id=NEW.id;
  SELECT * INTO b FROM inventory_production_batches WHERE id=r.production_batch_id;
 ELSE SELECT * INTO b FROM inventory_production_batches WHERE id=NEW.id;
 END IF;
 SELECT * INTO v FROM inventory_recipe_versions WHERE id=b.recipe_version_id;
 SELECT * INTO t FROM inventory_transactions WHERE id=b.inventory_transaction_id;
 SELECT * INTO u FROM inventory_units WHERE id=b.output_unit_id;
 IF t.id IS NULL OR t.type<>'production' OR t.source_type<>'production' OR t.source_id IS DISTINCT FROM b.id::text
 OR t.created_by IS DISTINCT FROM b.created_by OR t.effective_date IS DISTINCT FROM b.effective_date
 OR b.output_inventory_item_id IS DISTINCT FROM v.output_inventory_item_id OR NOT v.is_sealed
 OR b.expected_output_base_quantity IS DISTINCT FROM v.expected_output_base_quantity
 OR b.actual_output_base_quantity IS DISTINCT FROM b.actual_output_quantity*u.factor_to_base
 OR u.family IS DISTINCT FROM (SELECT unit_family FROM inventory_items WHERE id=b.output_inventory_item_id) THEN
  RAISE EXCEPTION 'Production batch provenance or actual conversion mismatch' USING ERRCODE='23514';
 END IF;
 -- Formula and movements are compared as multisets; no extra or duplicate facts can hide behind a count.
 IF EXISTS((SELECT inventory_item_id,quantity FROM inventory_stock_movements WHERE inventory_transaction_id=t.id
 EXCEPT ALL (SELECT inventory_item_id,-base_quantity FROM inventory_recipe_ingredients WHERE recipe_version_id=v.id
 UNION ALL SELECT b.output_inventory_item_id,b.actual_output_base_quantity)))
 OR (SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=t.id)<>(SELECT count(*)+1 FROM inventory_recipe_ingredients WHERE recipe_version_id=v.id) THEN
  RAISE EXCEPTION 'Production movements must match recipe and actual output' USING ERRCODE='23514';
 END IF;
 SELECT count(*),count(allocated_cost),COALESCE(sum(allocated_cost),0) INTO n,known,subtotal
 FROM inventory_production_cost_allocations WHERE production_batch_id=b.id AND reversal_of_id IS NULL;
 IF n<>(SELECT count(*) FROM inventory_recipe_ingredients WHERE recipe_version_id=v.id)
 OR (b.total_cost IS NOT NULL AND (known<>n OR b.total_cost<>subtotal)) OR (b.total_cost IS NULL AND known=n)
 OR EXISTS(SELECT 1 FROM inventory_production_cost_allocations a
 LEFT JOIN inventory_stock_movements m ON m.id=a.stock_movement_id LEFT JOIN inventory_cost_layers l ON l.id=a.inventory_cost_layer_id
 WHERE a.production_batch_id=b.id AND a.reversal_of_id IS NULL AND (m.inventory_transaction_id IS DISTINCT FROM t.id
 OR m.quantity IS DISTINCT FROM -a.allocated_quantity OR a.method<>'moving_weighted_average'
 OR (a.allocated_cost IS NULL) IS DISTINCT FROM (a.inventory_cost_layer_id IS NULL)
 OR (a.allocated_cost IS NOT NULL AND (l.stock_movement_id IS DISTINCT FROM m.id OR l.total_cost IS DISTINCT FROM -a.allocated_cost OR l.valuation_warning IS DISTINCT FROM a.fallback)))) THEN
  RAISE EXCEPTION 'Production allocations and cost coverage must reconcile' USING ERRCODE='23514';
 END IF;
 IF b.cost_coverage IS DISTINCT FROM (CASE WHEN known=n THEN
    CASE WHEN EXISTS(SELECT 1 FROM inventory_production_cost_allocations WHERE production_batch_id=b.id AND reversal_of_id IS NULL AND fallback IS NOT NULL) THEN 'unreliable' ELSE 'complete' END
   WHEN known=0 THEN 'unknown' ELSE 'partial' END)
 OR jsonb_typeof(b.operational_warnings)<>'array'
 OR jsonb_array_length(b.operational_warnings)<>(SELECT count(*) FROM inventory_production_cost_allocations WHERE production_batch_id=b.id AND reversal_of_id IS NULL AND fallback IS NOT NULL)
 OR EXISTS(SELECT 1 FROM inventory_production_cost_allocations a JOIN inventory_stock_movements m ON m.id=a.stock_movement_id
    WHERE a.production_batch_id=b.id AND a.reversal_of_id IS NULL AND a.fallback IS NOT NULL
    AND NOT b.operational_warnings @> jsonb_build_array(jsonb_build_object('inventory_item_id',m.inventory_item_id,'code',a.fallback)))
 OR EXISTS(SELECT 1 FROM inventory_cost_layers WHERE inventory_transaction_id=t.id AND inventory_item_id=b.output_inventory_item_id
   AND valuation_warning IS DISTINCT FROM (CASE WHEN b.cost_coverage='unreliable' THEN 'unreliable_input' ELSE NULL END)) THEN
  RAISE EXCEPTION 'Production coverage and warning provenance must reconcile' USING ERRCODE='23514';
 END IF;
 IF (b.total_cost IS NULL AND EXISTS(SELECT 1 FROM inventory_cost_layers WHERE inventory_transaction_id=t.id AND inventory_item_id=b.output_inventory_item_id))
 OR (b.total_cost IS NOT NULL AND (SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=t.id AND inventory_item_id=b.output_inventory_item_id AND total_cost=b.total_cost)<>1)
 OR (SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=t.id)<>known+(CASE WHEN b.total_cost IS NULL THEN 0 ELSE 1 END) THEN
  RAISE EXCEPTION 'Output cost must transfer known input costs, never invent zero' USING ERRCODE='23514';
 END IF;
 IF r.id IS NOT NULL THEN
  SELECT * INTO o FROM inventory_transactions WHERE id=r.inventory_transaction_id;
  IF o.reversal_of_id IS DISTINCT FROM t.id OR o.type<>'reversal' OR o.source_type<>'reversal' OR o.source_id IS DISTINCT FROM t.id::text
  OR o.effective_date IS DISTINCT FROM r.effective_date OR o.created_by IS DISTINCT FROM r.created_by OR btrim(r.reason)='' THEN
   RAISE EXCEPTION 'Production reversal provenance mismatch' USING ERRCODE='23514';
  END IF;
  IF (SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=o.id)<>(SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=t.id)
  OR EXISTS(SELECT inventory_item_id,unit_id,quantity FROM inventory_stock_movements WHERE inventory_transaction_id=o.id
   EXCEPT ALL SELECT inventory_item_id,unit_id,-quantity FROM inventory_stock_movements WHERE inventory_transaction_id=t.id)
  OR (SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=o.id)<>(SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=t.id)
  OR (SELECT count(*) FROM inventory_production_cost_allocations WHERE production_batch_id=b.id AND reversal_of_id IS NOT NULL)<>n
  OR EXISTS(SELECT 1 FROM inventory_production_cost_allocations a JOIN inventory_production_cost_allocations orig ON orig.id=a.reversal_of_id
   JOIN inventory_stock_movements m ON m.id=a.stock_movement_id LEFT JOIN inventory_cost_layers l ON l.id=a.inventory_cost_layer_id
   WHERE a.production_batch_id=b.id AND (orig.production_batch_id IS DISTINCT FROM b.id OR a.allocated_quantity IS DISTINCT FROM -orig.allocated_quantity
   OR a.allocated_cost IS DISTINCT FROM -orig.allocated_cost OR a.basis_quantity IS DISTINCT FROM -orig.basis_quantity
   OR a.basis_total_cost IS DISTINCT FROM -orig.basis_total_cost OR a.method IS DISTINCT FROM orig.method OR a.fallback IS DISTINCT FROM orig.fallback
   OR m.inventory_transaction_id IS DISTINCT FROM o.id OR m.quantity IS DISTINCT FROM -a.allocated_quantity
   OR (orig.inventory_cost_layer_id IS NULL) IS DISTINCT FROM (a.inventory_cost_layer_id IS NULL)
   OR (orig.inventory_cost_layer_id IS NOT NULL AND l.reversal_of_id IS DISTINCT FROM orig.inventory_cost_layer_id))) THEN
   RAISE EXCEPTION 'Production reversal must exactly compensate historical facts' USING ERRCODE='23514';
  END IF;
 END IF;
 RETURN NEW;
END; $$;
CREATE TRIGGER inventory_recipe_version_guard BEFORE INSERT OR UPDATE OR DELETE ON inventory_recipe_versions FOR EACH ROW EXECUTE FUNCTION inventory_recipe_guard();
CREATE TRIGGER inventory_recipe_ingredient_guard BEFORE INSERT OR UPDATE OR DELETE ON inventory_recipe_ingredients FOR EACH ROW EXECUTE FUNCTION inventory_recipe_guard();
CREATE CONSTRAINT TRIGGER inventory_recipe_version_sealed AFTER INSERT ON inventory_recipe_versions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_recipe_sealed_at_commit();
CREATE TRIGGER inventory_recipe_item_guard BEFORE UPDATE ON inventory_items FOR EACH ROW EXECUTE FUNCTION inventory_recipe_item_guard();
CREATE TRIGGER inventory_production_insert_guard BEFORE INSERT ON inventory_production_batches FOR EACH ROW EXECUTE FUNCTION inventory_production_insert_guard();
CREATE TRIGGER inventory_production_basis_guard BEFORE INSERT ON inventory_production_cost_allocations FOR EACH ROW EXECUTE FUNCTION inventory_production_basis_guard();
CREATE TRIGGER inventory_production_batches_immutable BEFORE UPDATE OR DELETE ON inventory_production_batches FOR EACH ROW EXECUTE FUNCTION inventory_production_history_guard();
CREATE TRIGGER inventory_production_allocations_immutable BEFORE UPDATE OR DELETE ON inventory_production_cost_allocations FOR EACH ROW EXECUTE FUNCTION inventory_prevent_history_mutation();
CREATE TRIGGER inventory_production_reversals_immutable BEFORE UPDATE OR DELETE ON inventory_production_reversals FOR EACH ROW EXECUTE FUNCTION inventory_prevent_history_mutation();
CREATE TRIGGER inventory_production_allocations_append BEFORE INSERT ON inventory_production_cost_allocations FOR EACH ROW EXECUTE FUNCTION inventory_production_append_guard();
CREATE TRIGGER inventory_production_movements_append BEFORE INSERT ON inventory_stock_movements FOR EACH ROW EXECUTE FUNCTION inventory_production_append_guard();
CREATE TRIGGER inventory_production_layers_append BEFORE INSERT ON inventory_cost_layers FOR EACH ROW EXECUTE FUNCTION inventory_production_append_guard();
CREATE CONSTRAINT TRIGGER inventory_production_batches_reconcile AFTER INSERT OR UPDATE ON inventory_production_batches DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_production_reconcile();
CREATE CONSTRAINT TRIGGER inventory_production_reversals_reconcile AFTER INSERT ON inventory_production_reversals DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_production_reconcile();
CREATE CONSTRAINT TRIGGER inventory_production_transaction_source AFTER INSERT ON inventory_transactions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_production_reconcile();
SQL);
    }

    public function down(): void
    {
        // Rollback is permitted only at an empty aggregate boundary, never by deleting history.
        if (DB::table('inventory_recipes')->exists() || DB::table('inventory_production_batches')->exists()) {
            throw new RuntimeException('Recipes/Production rollback requires an empty aggregate boundary.');
        }
        DB::unprepared(<<<'SQL'
DROP TRIGGER inventory_recipe_item_guard ON inventory_items;
DROP TRIGGER inventory_production_movements_append ON inventory_stock_movements;
DROP TRIGGER inventory_production_layers_append ON inventory_cost_layers;
DROP TRIGGER inventory_production_transaction_source ON inventory_transactions;
SQL);
        foreach (['inventory_production_reversals', 'inventory_production_cost_allocations', 'inventory_production_batches', 'inventory_recipe_ingredients', 'inventory_recipe_versions', 'inventory_recipes'] as $table) {
            Schema::drop($table);
        }
        foreach (['inventory_recipe_guard', 'inventory_recipe_sealed_at_commit', 'inventory_recipe_item_guard', 'inventory_production_insert_guard', 'inventory_production_basis_guard', 'inventory_production_history_guard', 'inventory_production_append_guard', 'inventory_production_reconcile'] as $function) {
            DB::statement("DROP FUNCTION {$function}()");
        }
        DB::statement('ALTER TABLE inventory_cost_layers DROP CONSTRAINT inventory_cost_layers_reversal_shape_valid');
        DB::statement('ALTER TABLE inventory_cost_layers ADD CONSTRAINT inventory_cost_layers_reversal_shape_valid CHECK((reversal_of_id IS NULL AND quantity>0 AND total_cost>=0) OR (reversal_of_id IS NOT NULL AND quantity<0 AND total_cost<=0))');
        // Restore the exact AOB-2 validation contract, including transaction-bound original linkage.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION inventory_validate_cost_layer() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE m inventory_stock_movements%ROWTYPE; t inventory_transactions%ROWTYPE; o inventory_cost_layers%ROWTYPE;
BEGIN
 SELECT * INTO m FROM inventory_stock_movements WHERE id=NEW.stock_movement_id;
 SELECT * INTO t FROM inventory_transactions WHERE id=NEW.inventory_transaction_id;
 IF m.id IS NULL OR NEW.inventory_item_id IS DISTINCT FROM m.inventory_item_id OR NEW.inventory_transaction_id IS DISTINCT FROM m.inventory_transaction_id OR NEW.quantity IS DISTINCT FROM m.quantity OR NEW.effective_date IS DISTINCT FROM t.effective_date THEN
  RAISE EXCEPTION 'Cost layer must exactly match its source movement' USING ERRCODE='23514'; END IF;
 IF NEW.reversal_of_id IS NULL THEN
  IF NEW.quantity<=0 OR NEW.total_cost<0 OR t.type='reversal' THEN RAISE EXCEPTION 'Original cost layer requires a positive receipt' USING ERRCODE='23514'; END IF;
 ELSE
  SELECT * INTO o FROM inventory_cost_layers WHERE id=NEW.reversal_of_id;
  IF o.id IS NULL OR o.reversal_of_id IS NOT NULL OR t.type<>'reversal' OR t.reversal_of_id IS DISTINCT FROM o.inventory_transaction_id OR NEW.inventory_item_id IS DISTINCT FROM o.inventory_item_id OR NEW.quantity IS DISTINCT FROM -o.quantity OR NEW.total_cost IS DISTINCT FROM -o.total_cost THEN
   RAISE EXCEPTION 'Cost reversal must exactly compensate the original transaction layer' USING ERRCODE='23514'; END IF;
 END IF; RETURN NEW;
END; $$;
SQL);
        Schema::table('inventory_cost_layers', function (Blueprint $t): void {
            $t->dropColumn('valuation_warning');
        });
    }
};
