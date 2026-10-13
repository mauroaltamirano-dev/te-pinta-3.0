<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_product_consumption_definitions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('product_id')->unique()->constrained('products')->restrictOnDelete();
            $t->string('mode', 20);
            $t->unsignedBigInteger('current_version_id')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('inventory_product_consumption_versions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('definition_id')->constrained('inventory_product_consumption_definitions')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('product_name_snapshot');
            $t->boolean('is_active')->default(false);
            $t->boolean('is_sealed')->default(false);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['definition_id', 'version']);
        });
        Schema::table('inventory_product_consumption_definitions', function (Blueprint $t): void {
            $t->foreign('current_version_id')->references('id')->on('inventory_product_consumption_versions')->restrictOnDelete();
        });
        Schema::create('inventory_product_consumption_components', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('version_id')->constrained('inventory_product_consumption_versions')->restrictOnDelete();
            $t->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $t->decimal('quantity', 20, 6);
            $t->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $t->decimal('base_quantity', 20, 6);
            $t->timestamps();
            $t->unique(['version_id', 'inventory_item_id']);
        });
        Schema::create('order_inventory_consumptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $t->foreignId('inventory_transaction_id')->nullable()->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $t->date('effective_date');
            $t->bigInteger('total_cost')->nullable();
            $t->string('cost_coverage', 20);
            $t->jsonb('warnings');
            $t->jsonb('products_snapshot');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('order_inventory_consumption_components', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_inventory_consumption_id')->constrained('order_inventory_consumptions')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->string('product_name_snapshot');
            $t->foreignId('consumption_version_id')->constrained('inventory_product_consumption_versions')->restrictOnDelete();
            $t->foreignId('definition_component_id')->constrained('inventory_product_consumption_components')->restrictOnDelete();
            $t->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $t->string('inventory_item_name_snapshot');
            $t->decimal('quantity', 20, 6);
            $t->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $t->string('unit_code_snapshot', 12);
            $t->string('base_unit_code_snapshot', 12);
            $t->decimal('component_base_quantity', 20, 6);
            $t->unsignedInteger('commercial_quantity');
            $t->decimal('consumed_base_quantity', 20, 6);
            $t->foreignId('stock_movement_id')->constrained('inventory_stock_movements')->restrictOnDelete();
            $t->foreignId('inventory_cost_layer_id')->nullable()->constrained('inventory_cost_layers')->restrictOnDelete();
            $t->decimal('basis_quantity', 20, 6);
            $t->bigInteger('basis_total_cost');
            $t->bigInteger('allocated_cost')->nullable();
            $t->string('method', 40);
            $t->string('fallback', 40)->nullable();
            $t->timestamps();
            $t->unique(['order_inventory_consumption_id', 'definition_component_id'], 'order_consumption_component_identity');
        });
        Schema::create('order_inventory_consumption_reversals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_inventory_consumption_id')->constrained('order_inventory_consumptions', indexName: 'order_consumption_reversal_source_fk')->restrictOnDelete();
            $t->unique('order_inventory_consumption_id', 'order_consumption_one_reversal');
            $t->foreignId('inventory_transaction_id')->nullable()->constrained('inventory_transactions', indexName: 'order_consumption_reversal_tx_fk')->restrictOnDelete();
            $t->unique('inventory_transaction_id', 'order_consumption_reversal_tx_unique');
            $t->date('effective_date');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        DB::unprepared(<<<'SQL'
ALTER TABLE inventory_product_consumption_definitions ADD CHECK(mode IN ('consume','no_inventory'));
ALTER TABLE inventory_product_consumption_versions ADD CHECK(version>0), ADD COLUMN creation_xid xid8 NOT NULL DEFAULT pg_current_xact_id();
CREATE UNIQUE INDEX inventory_consumption_one_active ON inventory_product_consumption_versions(definition_id) WHERE is_active;
ALTER TABLE inventory_product_consumption_components ADD CHECK(quantity>0 AND base_quantity>0);
ALTER TABLE order_inventory_consumptions ADD COLUMN creation_xid xid8 NOT NULL DEFAULT pg_current_xact_id(), ADD CHECK(total_cost>=0),
 ADD CHECK(cost_coverage IN ('complete','unreliable','unknown','partial')), ADD CHECK(jsonb_typeof(products_snapshot)='array' AND jsonb_array_length(products_snapshot)>0), ADD CHECK(jsonb_typeof(warnings)='array');
ALTER TABLE order_inventory_consumption_reversals ADD COLUMN creation_xid xid8 NOT NULL DEFAULT pg_current_xact_id();
ALTER TABLE order_inventory_consumption_components ADD CHECK(quantity>0 AND component_base_quantity>0 AND commercial_quantity>0 AND consumed_base_quantity>0),
 ADD CHECK(consumed_base_quantity=component_base_quantity*commercial_quantity), ADD CHECK(allocated_cost>=0), ADD CHECK(method='moving_weighted_average'),
 ADD CHECK(fallback IS NULL OR fallback IN ('no_cost_history','partial_valuation','negative_stock_latest_basis','unreliable_input','insufficient_stock')),
 ADD CHECK((allocated_cost IS NULL)=(inventory_cost_layer_id IS NULL));

CREATE OR REPLACE FUNCTION inventory_consumption_definition_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE v inventory_product_consumption_versions%ROWTYPE; i inventory_items%ROWTYPE; u inventory_units%ROWTYPE; d inventory_product_consumption_definitions%ROWTYPE;
BEGIN
 IF TG_TABLE_NAME='inventory_product_consumption_definitions' THEN
  IF TG_OP='DELETE' OR (TG_OP='UPDATE' AND (to_jsonb(NEW)-'current_version_id'-'updated_at') IS DISTINCT FROM (to_jsonb(OLD)-'current_version_id'-'updated_at')) THEN
   RAISE EXCEPTION 'Consumption identity and explicit mode are immutable' USING ERRCODE='55000';
  END IF;
  PERFORM 1 FROM products WHERE id=NEW.product_id FOR UPDATE;
  RETURN NEW;
 ELSIF TG_TABLE_NAME='inventory_product_consumption_versions' THEN
  IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Versions cannot be deleted' USING ERRCODE='55000'; END IF;
  IF TG_OP='INSERT' THEN
   SELECT * INTO d FROM inventory_product_consumption_definitions WHERE id=NEW.definition_id FOR UPDATE;
   IF d.mode<>'consume' OR NEW.is_active OR NEW.is_sealed OR NEW.creation_xid<>pg_current_xact_id()
    OR NEW.version<>COALESCE((SELECT MAX(version)+1 FROM inventory_product_consumption_versions WHERE definition_id=NEW.definition_id),1) THEN
    RAISE EXCEPTION 'Create an unsealed next consumption version' USING ERRCODE='23514'; END IF;
  ELSE
   IF (to_jsonb(NEW)-'is_active'-'is_sealed'-'updated_at') IS DISTINCT FROM (to_jsonb(OLD)-'is_active'-'is_sealed'-'updated_at') OR NOT (
    (NOT OLD.is_sealed AND NEW.is_sealed AND NEW.is_active AND OLD.creation_xid=pg_current_xact_id()) OR
    (OLD.is_sealed AND OLD.is_active AND NEW.is_sealed AND NOT NEW.is_active)) THEN
    RAISE EXCEPTION 'Consumption formula is immutable; create a version' USING ERRCODE='55000'; END IF;
  END IF;
  RETURN NEW;
 END IF;
 IF TG_OP<>'INSERT' THEN RAISE EXCEPTION 'Components are immutable' USING ERRCODE='55000'; END IF;
 SELECT * INTO v FROM inventory_product_consumption_versions WHERE id=NEW.version_id FOR UPDATE;
 SELECT * INTO i FROM inventory_items WHERE id=NEW.inventory_item_id FOR UPDATE;
 SELECT * INTO u FROM inventory_units WHERE id=NEW.unit_id FOR UPDATE;
 IF v.is_sealed OR v.creation_xid<>pg_current_xact_id() OR NOT i.is_active OR NOT i.tracks_stock OR NOT i.order_consumption_enabled
 OR i.unit_family<>u.family OR NEW.base_quantity<>NEW.quantity*u.factor_to_base OR trunc(NEW.base_quantity,i.quantity_precision)<>NEW.base_quantity THEN
  RAISE EXCEPTION 'Invalid or late consumption component' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END; $$;
CREATE OR REPLACE FUNCTION inventory_consumption_definition_reconcile() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE d inventory_product_consumption_definitions%ROWTYPE;
BEGIN
 IF TG_TABLE_NAME='inventory_product_consumption_definitions' THEN SELECT * INTO d FROM inventory_product_consumption_definitions WHERE id=NEW.id;
 ELSE SELECT * INTO d FROM inventory_product_consumption_definitions WHERE id=NEW.definition_id; END IF;
 IF d.mode='no_inventory' THEN
  IF d.current_version_id IS NOT NULL OR EXISTS(SELECT 1 FROM inventory_product_consumption_versions WHERE definition_id=d.id) THEN RAISE EXCEPTION 'No inventory has no physical formula' USING ERRCODE='23514'; END IF;
 ELSE
  IF NOT EXISTS(SELECT 1 FROM inventory_product_consumption_versions v WHERE v.id=d.current_version_id AND v.definition_id=d.id AND v.is_active AND v.is_sealed AND EXISTS(SELECT 1 FROM inventory_product_consumption_components WHERE version_id=v.id))
   OR EXISTS(SELECT 1 FROM inventory_product_consumption_versions WHERE definition_id=d.id AND (NOT is_sealed OR (is_active AND id<>d.current_version_id))) THEN
   RAISE EXCEPTION 'Consumption requires one sealed current formula' USING ERRCODE='23514'; END IF;
 END IF;
 RETURN NULL;
END; $$;
CREATE OR REPLACE FUNCTION inventory_consumption_item_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM inventory_product_consumption_components WHERE inventory_item_id=OLD.id) AND (NEW.base_unit_id<>OLD.base_unit_id OR NEW.unit_family<>OLD.unit_family OR NEW.quantity_precision<>OLD.quantity_precision) THEN
  RAISE EXCEPTION 'Historical consumption item quantity identity is immutable' USING ERRCODE='55000'; END IF;
 IF (NOT NEW.is_active OR NOT NEW.tracks_stock OR NOT NEW.order_consumption_enabled) AND EXISTS(SELECT 1 FROM inventory_product_consumption_components c JOIN inventory_product_consumption_versions v ON v.id=c.version_id WHERE c.inventory_item_id=OLD.id AND v.is_active) THEN
  RAISE EXCEPTION 'Current sale consumption requires eligible inventory items' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END; $$;
CREATE TRIGGER inventory_consumption_definition_guard BEFORE INSERT OR UPDATE OR DELETE ON inventory_product_consumption_definitions FOR EACH ROW EXECUTE FUNCTION inventory_consumption_definition_guard();
CREATE TRIGGER inventory_consumption_version_guard BEFORE INSERT OR UPDATE OR DELETE ON inventory_product_consumption_versions FOR EACH ROW EXECUTE FUNCTION inventory_consumption_definition_guard();
CREATE TRIGGER inventory_consumption_component_guard BEFORE INSERT OR UPDATE OR DELETE ON inventory_product_consumption_components FOR EACH ROW EXECUTE FUNCTION inventory_consumption_definition_guard();
CREATE CONSTRAINT TRIGGER inventory_consumption_definition_reconcile AFTER INSERT OR UPDATE ON inventory_product_consumption_definitions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_consumption_definition_reconcile();
CREATE CONSTRAINT TRIGGER inventory_consumption_version_reconcile AFTER INSERT OR UPDATE ON inventory_product_consumption_versions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_consumption_definition_reconcile();
CREATE TRIGGER inventory_consumption_item_guard BEFORE UPDATE ON inventory_items FOR EACH ROW EXECUTE FUNCTION inventory_consumption_item_guard();

CREATE OR REPLACE FUNCTION inventory_order_consumption_history_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE o orders%ROWTYPE; source_xid xid8;
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Order physical history cannot be deleted' USING ERRCODE='55000'; END IF;
 IF TG_TABLE_NAME='order_inventory_consumption_components' THEN
  SELECT creation_xid INTO source_xid FROM order_inventory_consumptions WHERE id=NEW.order_inventory_consumption_id;
  IF TG_OP<>'INSERT' OR source_xid<>pg_current_xact_id() THEN RAISE EXCEPTION 'Order components are immutable; no late append' USING ERRCODE='55000'; END IF;
  RETURN NEW;
 END IF;
 IF TG_OP='INSERT' AND NEW.inventory_transaction_id IS NOT NULL THEN
  RAISE EXCEPTION 'Create the semantic source before linking its captured physical transaction' USING ERRCODE='23514';
 END IF;
 IF TG_OP='UPDATE' THEN
  IF OLD.creation_xid<>pg_current_xact_id() OR OLD.inventory_transaction_id IS NOT NULL OR NEW.inventory_transaction_id IS NULL OR NEW.creation_xid IS DISTINCT FROM OLD.creation_xid
   OR (TG_TABLE_NAME='order_inventory_consumptions' AND (to_jsonb(NEW)-'inventory_transaction_id'-'total_cost'-'cost_coverage'-'warnings'-'updated_at') IS DISTINCT FROM (to_jsonb(OLD)-'inventory_transaction_id'-'total_cost'-'cost_coverage'-'warnings'-'updated_at'))
   OR (TG_TABLE_NAME='order_inventory_consumption_reversals' AND (to_jsonb(NEW)-'inventory_transaction_id'-'updated_at') IS DISTINCT FROM (to_jsonb(OLD)-'inventory_transaction_id'-'updated_at')) THEN
   RAISE EXCEPTION 'Only same-transaction completion of physical history is allowed' USING ERRCODE='55000'; END IF;
 ELSIF NEW.creation_xid<>pg_current_xact_id() THEN RAISE EXCEPTION 'Invalid creation transaction' USING ERRCODE='23514'; END IF;
 IF TG_TABLE_NAME='order_inventory_consumptions' THEN
  SELECT * INTO o FROM orders WHERE id=NEW.order_id FOR UPDATE;
  IF o.status<>'delivered' THEN RAISE EXCEPTION 'Consumption requires delivered order' USING ERRCODE='23514'; END IF;
  IF TG_OP='INSERT' THEN
   PERFORM 1 FROM products WHERE id IN (SELECT (s->>'product_id')::bigint FROM jsonb_array_elements(NEW.products_snapshot) s) ORDER BY id FOR UPDATE;
   PERFORM 1 FROM inventory_product_consumption_definitions WHERE product_id IN (SELECT (s->>'product_id')::bigint FROM jsonb_array_elements(NEW.products_snapshot) s) ORDER BY product_id FOR UPDATE;
   PERFORM 1 FROM inventory_product_consumption_versions WHERE id IN (SELECT (s->>'version_id')::bigint FROM jsonb_array_elements(NEW.products_snapshot) s) ORDER BY id FOR UPDATE;
   IF EXISTS(SELECT 1 FROM jsonb_array_elements(NEW.products_snapshot) s LEFT JOIN inventory_product_consumption_definitions d ON d.product_id=(s->>'product_id')::bigint
    WHERE d.id IS NULL OR d.mode IS DISTINCT FROM s->>'mode' OR d.current_version_id IS DISTINCT FROM (s->>'version_id')::bigint
     OR (d.mode='consume' AND NOT EXISTS(SELECT 1 FROM inventory_product_consumption_versions v WHERE v.id=d.current_version_id AND v.is_active AND v.is_sealed))) THEN
    RAISE EXCEPTION 'Capture the locked current consumption definition, never a retired formula' USING ERRCODE='23514'; END IF;
  END IF;
 ELSE
  SELECT ord.* INTO o FROM orders ord JOIN order_inventory_consumptions c ON c.order_id=ord.id WHERE c.id=NEW.order_inventory_consumption_id FOR UPDATE OF ord;
  IF o.status<>'cancelled' THEN RAISE EXCEPTION 'Reversal requires cancelled delivered order' USING ERRCODE='23514'; END IF;
 END IF;
 RETURN NEW;
END; $$;
CREATE OR REPLACE FUNCTION inventory_order_consumption_append_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE t inventory_transactions%ROWTYPE; source_xid xid8;
BEGIN
 SELECT * INTO t FROM inventory_transactions WHERE id=NEW.inventory_transaction_id;
 IF t.type='sale_consumption' THEN
  SELECT creation_xid INTO source_xid FROM order_inventory_consumptions WHERE order_id::text=t.source_id;
 ELSIF t.type='reversal' AND EXISTS(SELECT 1 FROM inventory_transactions WHERE id=t.reversal_of_id AND type='sale_consumption') THEN
  SELECT r.creation_xid INTO source_xid FROM order_inventory_consumption_reversals r JOIN order_inventory_consumptions c ON c.id=r.order_inventory_consumption_id WHERE c.inventory_transaction_id=t.reversal_of_id;
 ELSE RETURN NEW;
 END IF;
 IF source_xid IS NULL OR source_xid<>pg_current_xact_id() THEN RAISE EXCEPTION 'Order movement/layer requires uncommitted semantic fact' USING ERRCODE='55000'; END IF;
 RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION inventory_order_consumption_reconcile() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE c order_inventory_consumptions%ROWTYPE; r order_inventory_consumption_reversals%ROWTYPE; t inventory_transactions%ROWTYPE; original_tx bigint; group_count int; known_count int; sum_cost bigint; derived_coverage text; g record; stock numeric; bq numeric; bc bigint; lq numeric; lc bigint; expected_cost bigint; expected_fallback text; expected_bq numeric; expected_bc bigint;
BEGIN
 IF TG_TABLE_NAME='inventory_transactions' THEN
  IF NEW.type='sale_consumption' THEN
   SELECT * INTO c FROM order_inventory_consumptions WHERE inventory_transaction_id=NEW.id;
   IF c.id IS NULL THEN RAISE EXCEPTION 'Sale consumption requires exact semantic source' USING ERRCODE='23514'; END IF;
  ELSIF NEW.type='reversal' AND EXISTS(SELECT 1 FROM inventory_transactions WHERE id=NEW.reversal_of_id AND type='sale_consumption') THEN
   SELECT * INTO r FROM order_inventory_consumption_reversals WHERE inventory_transaction_id=NEW.id;
   IF r.id IS NULL THEN RAISE EXCEPTION 'Sale reversal requires exact semantic source' USING ERRCODE='23514'; END IF;
  ELSE RETURN NULL; END IF;
 ELSIF TG_TABLE_NAME='order_inventory_consumptions' THEN SELECT * INTO c FROM order_inventory_consumptions WHERE id=NEW.id;
 ELSE SELECT * INTO r FROM order_inventory_consumption_reversals WHERE id=NEW.id;
 END IF;
 IF r.id IS NOT NULL THEN
  SELECT inventory_transaction_id INTO original_tx FROM order_inventory_consumptions WHERE id=r.order_inventory_consumption_id;
  IF original_tx IS NULL THEN
   IF r.inventory_transaction_id IS NOT NULL THEN RAISE EXCEPTION 'No inventory reversal has no transaction' USING ERRCODE='23514'; END IF;
   RETURN NULL;
  END IF;
  SELECT * INTO t FROM inventory_transactions WHERE id=r.inventory_transaction_id;
  IF t.id IS NULL OR t.type<>'reversal' OR t.source_type<>'reversal' OR t.source_id IS DISTINCT FROM 'order-consumption-reversal:'||r.order_inventory_consumption_id::text
   OR t.reversal_of_id IS DISTINCT FROM original_tx OR t.effective_date<>r.effective_date OR t.created_by<>r.created_by
   OR (SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=t.id)<>(SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=original_tx)
   OR EXISTS(SELECT 1 FROM inventory_stock_movements m WHERE m.inventory_transaction_id=original_tx AND NOT EXISTS(SELECT 1 FROM inventory_stock_movements opposite WHERE opposite.inventory_transaction_id=t.id AND opposite.inventory_item_id=m.inventory_item_id AND opposite.unit_id=m.unit_id AND opposite.quantity=-m.quantity))
   OR (SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=t.id)<>(SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=original_tx)
   OR EXISTS(SELECT 1 FROM inventory_cost_layers l WHERE l.inventory_transaction_id=original_tx AND NOT EXISTS(SELECT 1 FROM inventory_cost_layers opposite WHERE opposite.inventory_transaction_id=t.id AND opposite.reversal_of_id=l.id AND opposite.quantity=-l.quantity AND opposite.total_cost=-l.total_cost AND opposite.valuation_warning IS NOT DISTINCT FROM l.valuation_warning)) THEN
   RAISE EXCEPTION 'Order reversal must exactly compensate historical quantity/cost/provenance' USING ERRCODE='23514'; END IF;
  RETURN NULL;
 END IF;
 -- Reconcile explicit modes and commercial units without relying on mutable Product data.
 IF EXISTS(SELECT 1 FROM jsonb_array_elements(c.products_snapshot) s LEFT JOIN inventory_product_consumption_definitions d ON d.product_id=(s->>'product_id')::bigint WHERE d.id IS NULL OR d.mode IS DISTINCT FROM s->>'mode'
  OR (s->>'units')::int<>COALESCE((SELECT SUM(q) FROM (
   SELECT i.units_count q FROM order_items i WHERE i.order_id=c.order_id AND i.product_id=d.product_id AND NOT EXISTS(SELECT 1 FROM order_item_compositions WHERE order_item_id=i.id)
   UNION ALL SELECT p.quantity FROM order_item_compositions p JOIN order_items i ON i.id=p.order_item_id WHERE i.order_id=c.order_id AND p.product_id=d.product_id) sold),0)
  OR (d.mode='no_inventory' AND s->>'version_id' IS NOT NULL)
  OR (d.mode='consume' AND NOT EXISTS(SELECT 1 FROM inventory_product_consumption_versions v WHERE v.id=(s->>'version_id')::bigint AND v.definition_id=d.id AND v.is_sealed)))
  OR jsonb_array_length(c.products_snapshot)<>(SELECT count(DISTINCT (s->>'product_id')::bigint) FROM jsonb_array_elements(c.products_snapshot) s)
  OR (SELECT count(*) FROM jsonb_array_elements(c.products_snapshot))<>(SELECT count(DISTINCT product_id) FROM (SELECT i.product_id FROM order_items i WHERE i.order_id=c.order_id AND NOT EXISTS(SELECT 1 FROM order_item_compositions WHERE order_item_id=i.id) UNION ALL SELECT p.product_id FROM order_item_compositions p JOIN order_items i ON i.id=p.order_item_id WHERE i.order_id=c.order_id) sold)
  OR EXISTS(SELECT 1 FROM order_items WHERE order_id=c.order_id AND product_id IS NULL AND NOT EXISTS(SELECT 1 FROM order_item_compositions WHERE order_item_id=order_items.id)) THEN
  RAISE EXCEPTION 'Order semantic snapshots must reconcile commercial product units' USING ERRCODE='23514'; END IF;
 IF EXISTS(SELECT 1 FROM jsonb_array_elements(c.products_snapshot) s JOIN inventory_product_consumption_components component ON component.version_id=(s->>'version_id')::bigint
  WHERE NOT EXISTS(SELECT 1 FROM order_inventory_consumption_components fact WHERE fact.order_inventory_consumption_id=c.id AND fact.definition_component_id=component.id)) THEN
  RAISE EXCEPTION 'Order must snapshot every configured component' USING ERRCODE='23514'; END IF;
 SELECT count(DISTINCT stock_movement_id),count(DISTINCT stock_movement_id) FILTER(WHERE allocated_cost IS NOT NULL),sum(allocated_cost) INTO group_count,known_count,sum_cost FROM order_inventory_consumption_components WHERE order_inventory_consumption_id=c.id;
 derived_coverage=CASE WHEN group_count=known_count THEN CASE WHEN jsonb_array_length(c.warnings)=0 THEN 'complete' ELSE 'unreliable' END WHEN known_count=0 THEN 'unknown' ELSE 'partial' END;
 IF c.cost_coverage<>derived_coverage OR c.total_cost IS DISTINCT FROM (CASE WHEN group_count=known_count THEN COALESCE(sum_cost,0) ELSE NULL END) THEN
  RAISE EXCEPTION 'Historical COGS/coverage must reconcile allocated components' USING ERRCODE='23514'; END IF;
 IF group_count=0 THEN
  IF c.inventory_transaction_id IS NOT NULL OR EXISTS(SELECT 1 FROM jsonb_array_elements(c.products_snapshot) s WHERE s->>'mode'<>'no_inventory') THEN RAISE EXCEPTION 'Zero physical consumption requires explicit no inventory' USING ERRCODE='23514'; END IF;
  RETURN NULL;
 END IF;
 SELECT * INTO t FROM inventory_transactions WHERE id=c.inventory_transaction_id;
 IF t.id IS NULL OR t.type<>'sale_consumption' OR t.source_type<>'order' OR t.source_id IS DISTINCT FROM c.order_id::text OR t.effective_date<>c.effective_date OR t.created_by<>c.created_by
  OR group_count<>(SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=t.id)
  OR known_count<>(SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=t.id) THEN RAISE EXCEPTION 'Consumption requires exact transaction/movement/layer linkage' USING ERRCODE='23514'; END IF;
 IF EXISTS(SELECT 1 FROM order_inventory_consumption_components fact
  JOIN inventory_product_consumption_components component ON component.id=fact.definition_component_id
  JOIN inventory_product_consumption_versions v ON v.id=component.version_id JOIN inventory_product_consumption_definitions d ON d.id=v.definition_id
  JOIN inventory_items i ON i.id=fact.inventory_item_id JOIN inventory_units u ON u.id=fact.unit_id JOIN inventory_units b ON b.id=i.base_unit_id
  WHERE fact.order_inventory_consumption_id=c.id AND (fact.consumption_version_id<>v.id OR fact.product_id<>d.product_id OR fact.inventory_item_id<>component.inventory_item_id OR fact.quantity<>component.quantity OR fact.component_base_quantity<>component.base_quantity OR fact.unit_id<>component.unit_id
   OR fact.unit_code_snapshot<>u.code OR fact.base_unit_code_snapshot<>b.code OR (TG_NAME='inventory_order_cost_capture' AND fact.inventory_item_name_snapshot<>i.name)
   OR NOT EXISTS(SELECT 1 FROM jsonb_array_elements(c.products_snapshot) s WHERE (s->>'product_id')::bigint=fact.product_id AND s->>'name'=fact.product_name_snapshot AND (s->>'units')::int=fact.commercial_quantity AND (s->>'version_id')::bigint=v.id))) THEN
  RAISE EXCEPTION 'Historical component snapshots must match their formula and sold identity' USING ERRCODE='23514'; END IF;
 FOR g IN SELECT fact.inventory_item_id,fact.stock_movement_id,SUM(consumed_base_quantity) q,SUM(allocated_cost) cost,MIN(basis_quantity) basis_quantity,MIN(basis_total_cost) basis_cost,MIN(fallback) fallback,COUNT(DISTINCT basis_quantity) bqs,COUNT(DISTINCT basis_total_cost) bcs,COUNT(allocated_cost) known,COUNT(*) components,COUNT(DISTINCT inventory_cost_layer_id) layers FROM order_inventory_consumption_components fact WHERE order_inventory_consumption_id=c.id GROUP BY fact.inventory_item_id,fact.stock_movement_id LOOP
  IF NOT EXISTS(SELECT 1 FROM inventory_stock_movements m JOIN inventory_items i ON i.id=m.inventory_item_id WHERE m.id=g.stock_movement_id AND m.inventory_transaction_id=t.id AND m.inventory_item_id=g.inventory_item_id AND m.unit_id=i.base_unit_id AND m.quantity=-g.q AND i.is_active AND i.tracks_stock AND i.order_consumption_enabled)
   OR g.bqs<>1 OR g.bcs<>1 OR (g.known<>0 AND g.known<>g.components) OR g.layers>1
   OR (g.known>0 AND NOT EXISTS(SELECT 1 FROM inventory_cost_layers l WHERE l.stock_movement_id=g.stock_movement_id AND l.inventory_transaction_id=t.id AND l.total_cost=-g.cost AND l.valuation_warning IS NOT DISTINCT FROM g.fallback))
   OR EXISTS(SELECT 1 FROM order_inventory_consumption_components f WHERE f.order_inventory_consumption_id=c.id AND f.stock_movement_id=g.stock_movement_id AND (f.fallback IS DISTINCT FROM g.fallback OR (f.inventory_cost_layer_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM inventory_cost_layers WHERE id=f.inventory_cost_layer_id AND stock_movement_id=g.stock_movement_id)))) THEN
   RAISE EXCEPTION 'Aggregate item quantities and historical cost must reconcile' USING ERRCODE='23514'; END IF;
  IF TG_NAME='inventory_order_cost_capture' THEN
  SELECT COALESCE(SUM(quantity),0) INTO stock FROM inventory_stock_movements WHERE inventory_item_id=g.inventory_item_id AND inventory_transaction_id<>t.id;
  SELECT COALESCE(SUM(quantity),0),COALESCE(SUM(total_cost),0) INTO bq,bc FROM inventory_cost_layers WHERE inventory_item_id=g.inventory_item_id AND inventory_transaction_id<>t.id;
  SELECT quantity,total_cost INTO lq,lc FROM inventory_cost_layers WHERE inventory_item_id=g.inventory_item_id AND inventory_transaction_id<>t.id AND quantity>0 AND total_cost>0 ORDER BY effective_date DESC,id DESC LIMIT 1;
  expected_bq=bq; expected_bc=bc; expected_fallback=NULL;
  IF bq>0 AND bc>=0 THEN
   expected_cost=CASE WHEN g.q<=bq THEN round(bc*g.q/bq) ELSE bc+COALESCE(round(lc*(g.q-bq)/lq),0) END;
   IF g.q>bq THEN expected_fallback='negative_stock_latest_basis'; END IF;
  ELSE
   expected_bq=COALESCE(lq,0); expected_bc=COALESCE(lc,0); expected_cost=CASE WHEN lq IS NULL THEN NULL ELSE round(lc*g.q/lq) END;
   expected_fallback=CASE WHEN lq IS NULL THEN 'no_cost_history' ELSE 'negative_stock_latest_basis' END;
  END IF;
  IF stock>0 AND stock<>bq THEN expected_cost=NULL; expected_fallback='partial_valuation';
  ELSIF expected_fallback IS NULL AND EXISTS(SELECT 1 FROM inventory_cost_layers WHERE inventory_item_id=g.inventory_item_id AND inventory_transaction_id<>t.id AND valuation_warning IS NOT NULL GROUP BY inventory_item_id HAVING sum(quantity)>0) THEN expected_fallback='unreliable_input'; END IF;
  IF expected_cost IS NOT NULL AND expected_fallback IS NULL AND stock<g.q THEN expected_fallback='insufficient_stock'; END IF;
  IF g.basis_quantity IS DISTINCT FROM expected_bq OR g.basis_cost IS DISTINCT FROM expected_bc OR g.cost IS DISTINCT FROM expected_cost OR g.fallback IS DISTINCT FROM expected_fallback
   OR (g.fallback IS NOT NULL AND NOT c.warnings @> jsonb_build_array(jsonb_build_object('inventory_item_id',g.inventory_item_id,'code',g.fallback))) THEN
   RAISE EXCEPTION 'Cost allocation must match locked weighted valuation/provenance' USING ERRCODE='23514'; END IF;
  END IF;
 END LOOP;
 RETURN NULL;
END; $$;
CREATE TRIGGER inventory_order_cost_capture AFTER UPDATE OF inventory_transaction_id ON order_inventory_consumptions FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_reconcile();
CREATE TRIGGER inventory_order_consumption_history_guard BEFORE INSERT OR UPDATE OR DELETE ON order_inventory_consumptions FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_history_guard();
CREATE TRIGGER inventory_order_component_history_guard BEFORE INSERT OR UPDATE OR DELETE ON order_inventory_consumption_components FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_history_guard();
CREATE TRIGGER inventory_order_reversal_history_guard BEFORE INSERT OR UPDATE OR DELETE ON order_inventory_consumption_reversals FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_history_guard();
CREATE TRIGGER inventory_order_movements_append BEFORE INSERT ON inventory_stock_movements FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_append_guard();
CREATE TRIGGER inventory_order_layers_append BEFORE INSERT ON inventory_cost_layers FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_append_guard();
CREATE CONSTRAINT TRIGGER inventory_order_consumption_reconcile AFTER INSERT OR UPDATE ON order_inventory_consumptions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_reconcile();
CREATE CONSTRAINT TRIGGER inventory_order_reversal_reconcile AFTER INSERT OR UPDATE ON order_inventory_consumption_reversals DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_reconcile();
CREATE CONSTRAINT TRIGGER inventory_order_transaction_source AFTER INSERT ON inventory_transactions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_order_consumption_reconcile();

CREATE OR REPLACE FUNCTION inventory_order_economic_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE order_key bigint;
BEGIN
 IF TG_TABLE_NAME='orders' THEN
  order_key=OLD.id;
  IF EXISTS(SELECT 1 FROM order_inventory_consumptions WHERE order_id=order_key) THEN
   IF TG_OP='DELETE' OR (NEW.condition,NEW.delivery_type,NEW.source,NEW.total,NEW.discount_type,NEW.discount_value,NEW.items_subtotal,NEW.toppings_subtotal,NEW.items_base_subtotal,NEW.items_cooked_subtotal,NEW.raw_discount,NEW.cooking_surcharge,NEW.discount_amount,NEW.combined_dozen_savings,NEW.shipping_fee_snapshot) IS DISTINCT FROM (OLD.condition,OLD.delivery_type,OLD.source,OLD.total,OLD.discount_type,OLD.discount_value,OLD.items_subtotal,OLD.toppings_subtotal,OLD.items_base_subtotal,OLD.items_cooked_subtotal,OLD.raw_discount,OLD.cooking_surcharge,OLD.discount_amount,OLD.combined_dozen_savings,OLD.shipping_fee_snapshot) THEN RAISE EXCEPTION 'Fulfilled order economics cannot be rewritten' USING ERRCODE='55000'; END IF;
   IF NEW.status NOT IN ('delivered','cancelled') OR (NEW.status='delivered' AND EXISTS(SELECT 1 FROM order_inventory_consumption_reversals r JOIN order_inventory_consumptions c ON c.id=r.order_inventory_consumption_id WHERE c.order_id=order_key)) THEN RAISE EXCEPTION 'Invalid transition after physical evidence' USING ERRCODE='23514'; END IF;
  END IF;
 ELSE
  IF TG_TABLE_NAME='order_item_compositions' THEN SELECT order_id INTO order_key FROM order_items WHERE id=CASE WHEN TG_OP='DELETE' THEN OLD.order_item_id ELSE NEW.order_item_id END;
  ELSE order_key=CASE WHEN TG_OP='DELETE' THEN OLD.order_id ELSE NEW.order_id END; END IF;
  PERFORM 1 FROM orders WHERE id=order_key FOR UPDATE;
  IF EXISTS(SELECT 1 FROM order_inventory_consumptions WHERE order_id=order_key) THEN RAISE EXCEPTION 'Fulfilled commercial lines are immutable' USING ERRCODE='55000'; END IF;
  IF TG_OP='UPDATE' THEN
   IF TG_TABLE_NAME='order_item_compositions' THEN SELECT order_id INTO order_key FROM order_items WHERE id=OLD.order_item_id; ELSE order_key=OLD.order_id; END IF;
   PERFORM 1 FROM orders WHERE id=order_key FOR UPDATE;
   IF EXISTS(SELECT 1 FROM order_inventory_consumptions WHERE order_id=order_key) THEN RAISE EXCEPTION 'Cannot move fulfilled lines to another order' USING ERRCODE='55000'; END IF;
  END IF;
 END IF;
 IF TG_OP='DELETE' THEN RETURN OLD; END IF; RETURN NEW;
END; $$;
CREATE OR REPLACE FUNCTION inventory_order_fulfillment_reconcile() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.status='delivered' AND OLD.status<>'delivered' AND NOT EXISTS(SELECT 1 FROM order_inventory_consumptions WHERE order_id=NEW.id) THEN RAISE EXCEPTION 'Delivered transition requires consumption evidence' USING ERRCODE='23514'; END IF;
 IF NEW.status='cancelled' AND OLD.status='delivered' AND EXISTS(SELECT 1 FROM order_inventory_consumptions WHERE order_id=NEW.id) AND NOT EXISTS(SELECT 1 FROM order_inventory_consumption_reversals r JOIN order_inventory_consumptions c ON c.id=r.order_inventory_consumption_id WHERE c.order_id=NEW.id) THEN RAISE EXCEPTION 'Cancelled delivery requires historical reversal evidence' USING ERRCODE='23514'; END IF;
 RETURN NULL;
END; $$;
CREATE TRIGGER inventory_order_economic_guard BEFORE UPDATE OR DELETE ON orders FOR EACH ROW EXECUTE FUNCTION inventory_order_economic_guard();
CREATE TRIGGER inventory_order_items_economic_guard BEFORE INSERT OR UPDATE OR DELETE ON order_items FOR EACH ROW EXECUTE FUNCTION inventory_order_economic_guard();
CREATE TRIGGER inventory_order_compositions_economic_guard BEFORE INSERT OR UPDATE OR DELETE ON order_item_compositions FOR EACH ROW EXECUTE FUNCTION inventory_order_economic_guard();
CREATE TRIGGER inventory_order_addons_economic_guard BEFORE INSERT OR UPDATE OR DELETE ON order_toppings FOR EACH ROW EXECUTE FUNCTION inventory_order_economic_guard();
CREATE CONSTRAINT TRIGGER inventory_order_fulfillment_reconcile AFTER UPDATE OF status ON orders DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_order_fulfillment_reconcile();
SQL);
        $this->installCostValidator(true);
    }

    public function down(): void
    {
        if (DB::table('inventory_product_consumption_definitions')->exists() || DB::table('order_inventory_consumptions')->exists() || DB::table('order_inventory_consumption_reversals')->exists()) {
            throw new RuntimeException('Refusing to remove populated consumption history.');
        }
        $this->installCostValidator(false);
        foreach (['inventory_order_economic_guard' => 'orders', 'inventory_order_items_economic_guard' => 'order_items', 'inventory_order_compositions_economic_guard' => 'order_item_compositions', 'inventory_order_addons_economic_guard' => 'order_toppings', 'inventory_order_fulfillment_reconcile' => 'orders', 'inventory_consumption_item_guard' => 'inventory_items', 'inventory_order_movements_append' => 'inventory_stock_movements', 'inventory_order_layers_append' => 'inventory_cost_layers', 'inventory_order_transaction_source' => 'inventory_transactions'] as $trigger => $table) {
            DB::statement("DROP TRIGGER {$trigger} ON {$table}");
        }
        Schema::drop('order_inventory_consumption_reversals');
        Schema::drop('order_inventory_consumption_components');
        Schema::drop('order_inventory_consumptions');
        Schema::table('inventory_product_consumption_definitions', function (Blueprint $t): void {
            $t->dropForeign(['current_version_id']);
        });
        Schema::drop('inventory_product_consumption_components');
        Schema::drop('inventory_product_consumption_versions');
        Schema::drop('inventory_product_consumption_definitions');
        foreach (['inventory_consumption_definition_guard', 'inventory_consumption_definition_reconcile', 'inventory_consumption_item_guard', 'inventory_order_consumption_history_guard', 'inventory_order_consumption_append_guard', 'inventory_order_consumption_reconcile', 'inventory_order_economic_guard', 'inventory_order_fulfillment_reconcile'] as $function) {
            DB::statement("DROP FUNCTION {$function}()");
        }
    }

    private function installCostValidator(bool $orders): void
    {
        $condition = $orders
            ? "(NEW.quantity<0 AND t.type<>'production' AND NOT (t.type='adjustment' AND t.source_type='adjustment' AND EXISTS(SELECT 1 FROM inventory_adjustments WHERE 'inventory-adjustment:'||id::text=t.source_id AND reversal_of_id IS NULL)) AND NOT (t.type='sale_consumption' AND t.source_type='order' AND EXISTS(SELECT 1 FROM order_inventory_consumptions WHERE order_id::text=t.source_id)))"
            : "(NEW.quantity<0 AND t.type<>'production' AND NOT (t.type='adjustment' AND t.source_type='adjustment' AND EXISTS(SELECT 1 FROM inventory_adjustments WHERE 'inventory-adjustment:'||id::text=t.source_id AND reversal_of_id IS NULL)))";
        DB::unprepared(<<<SQL
CREATE OR REPLACE FUNCTION inventory_validate_cost_layer() RETURNS trigger LANGUAGE plpgsql AS \$\$
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
  IF t.type='reversal' OR {$condition} THEN
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
END; \$\$;
SQL);
    }
};
