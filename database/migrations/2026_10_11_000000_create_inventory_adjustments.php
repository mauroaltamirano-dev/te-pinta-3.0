<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $t->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $t->string('type', 32);
            $t->decimal('quantity', 20, 6);
            $t->decimal('delta', 20, 6);
            $t->decimal('stock_before', 20, 6);
            $t->decimal('physical_count', 20, 6)->nullable();
            $t->date('effective_date');
            $t->string('note', 240)->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('inventory_transaction_id')->nullable()->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $t->bigInteger('cost_total')->nullable();
            $t->string('cost_status', 16);
            $t->string('cost_coverage', 16);
            $t->decimal('cost_basis_quantity', 20, 6)->nullable();
            $t->bigInteger('cost_basis_total')->nullable();
            $t->string('cost_method', 32)->nullable();
            $t->string('cost_fallback', 32)->nullable();
            $t->jsonb('warnings')->default('[]');
            $t->uuid('idempotency_key')->unique();
            $t->string('request_fingerprint', 64);
            $t->foreignId('reversal_of_id')->nullable()->unique()->constrained('inventory_adjustments')->restrictOnDelete();
            $t->timestampsTz();
            $t->index(['inventory_item_id', 'effective_date']);
        });
        DB::unprepared(<<<'SQL'
ALTER TABLE inventory_adjustments ADD COLUMN creation_xid xid8 NOT NULL DEFAULT pg_current_xact_id();
ALTER TABLE inventory_adjustments ADD CONSTRAINT inventory_adjustment_shape_valid CHECK (
 type IN ('count_correction','waste','damage','expiry','manual_adjustment','reversal') AND delta<>0
 AND (type='reversal')=(reversal_of_id IS NOT NULL)
 AND (type NOT IN ('waste','damage','expiry') OR (quantity>0 AND delta<0))
 AND (type<>'manual_adjustment' OR quantity<>0)
 AND (type='count_correction')=(physical_count IS NOT NULL)
 AND (type<>'count_correction' OR quantity>=0)
 AND (physical_count IS NULL OR (physical_count>=0 AND delta=physical_count-stock_before))
 AND cost_status IN ('explicit','estimated','allocated','unknown','reversal')
 AND cost_coverage IN ('complete','unknown','unreliable')
 AND (cost_total IS NULL)=(cost_status='unknown')
 AND (cost_total IS NULL)=(cost_coverage='unknown')
 AND (cost_total IS NULL OR (delta>0 AND cost_total>=0) OR (delta<0 AND cost_total<=0))
 AND request_fingerprint ~ '^[0-9a-f]{64}$' AND (note IS NULL OR btrim(note)<>'') AND jsonb_typeof(warnings)='array'
);

CREATE OR REPLACE FUNCTION inventory_adjustment_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE i inventory_items%ROWTYPE; u inventory_units%ROWTYPE; o inventory_adjustments%ROWTYPE;
DECLARE stock numeric; q numeric; c bigint; latest_q numeric; latest_c bigint; warned numeric;
DECLARE bq numeric; bc bigint; cost bigint; fallback text; status text; coverage text;
BEGIN
 IF TG_OP<>'INSERT' THEN
  IF TG_OP='UPDATE' AND OLD.inventory_transaction_id IS NULL AND NEW.inventory_transaction_id IS NOT NULL
   AND OLD.creation_xid=pg_current_xact_id()
   AND (to_jsonb(NEW)-ARRAY['inventory_transaction_id','updated_at']) IS NOT DISTINCT FROM (to_jsonb(OLD)-ARRAY['inventory_transaction_id','updated_at']) THEN RETURN NEW; END IF;
  RAISE EXCEPTION 'Adjustment history is immutable' USING ERRCODE='55000';
 END IF;
 SELECT * INTO i FROM inventory_items WHERE id=NEW.inventory_item_id FOR UPDATE;
 SELECT * INTO u FROM inventory_units WHERE id=NEW.unit_id;
 SELECT COALESCE(sum(quantity),0) INTO stock FROM inventory_stock_movements WHERE inventory_item_id=i.id;
 IF NEW.inventory_transaction_id IS NOT NULL OR NEW.stock_before IS DISTINCT FROM stock OR i.id IS NULL THEN
  RAISE EXCEPTION 'Adjustment requires locked current stock snapshot' USING ERRCODE='23514';
 END IF;
 IF NEW.reversal_of_id IS NOT NULL THEN
  SELECT * INTO o FROM inventory_adjustments WHERE id=NEW.reversal_of_id FOR UPDATE;
  IF o.id IS NULL OR o.reversal_of_id IS NOT NULL OR o.inventory_transaction_id IS NULL
   OR NEW.inventory_item_id IS DISTINCT FROM o.inventory_item_id OR NEW.unit_id IS DISTINCT FROM o.unit_id
   OR NEW.quantity IS DISTINCT FROM -o.quantity OR NEW.delta IS DISTINCT FROM -o.delta
   OR NEW.cost_total IS DISTINCT FROM -o.cost_total OR NEW.cost_basis_quantity IS DISTINCT FROM o.cost_basis_quantity
   OR NEW.cost_basis_total IS DISTINCT FROM o.cost_basis_total OR NEW.cost_method IS DISTINCT FROM o.cost_method
   OR NEW.cost_fallback IS DISTINCT FROM o.cost_fallback OR NEW.warnings IS DISTINCT FROM o.warnings
   OR NEW.cost_coverage IS DISTINCT FROM o.cost_coverage OR NEW.cost_status IS DISTINCT FROM (CASE WHEN o.cost_total IS NULL THEN 'unknown' ELSE 'reversal' END)
   OR NEW.note IS NULL THEN RAISE EXCEPTION 'Adjustment reversal requires exact historical provenance' USING ERRCODE='23514'; END IF;
  RETURN NEW;
 END IF;
 IF NOT i.is_active OR NOT i.tracks_stock OR u.family IS DISTINCT FROM i.unit_family
  OR NEW.delta<>round(NEW.delta,i.quantity_precision)
  OR (CASE WHEN NEW.type='count_correction' THEN NEW.physical_count ELSE abs(NEW.delta) END) IS DISTINCT FROM abs(NEW.quantity)*u.factor_to_base
  OR (NEW.type='manual_adjustment' AND sign(NEW.delta)<>sign(NEW.quantity)) THEN
  RAISE EXCEPTION 'Adjustment quantity, item or conversion is invalid' USING ERRCODE='23514';
 END IF;
 SELECT COALESCE(sum(quantity),0),COALESCE(sum(total_cost),0),COALESCE(sum(quantity) FILTER(WHERE valuation_warning IS NOT NULL),0) INTO q,c,warned FROM inventory_cost_layers WHERE inventory_item_id=i.id;
 SELECT quantity,total_cost INTO latest_q,latest_c FROM inventory_cost_layers WHERE inventory_item_id=i.id AND quantity>0 AND total_cost>0 ORDER BY effective_date DESC,id DESC LIMIT 1;
 status='unknown';coverage='unknown';
 IF NEW.delta>0 THEN
  IF NEW.cost_status='explicit' THEN cost=NEW.cost_total;status='explicit';coverage='complete';
  ELSIF stock>0 AND stock=q AND c>=0 AND warned<=0 THEN
   cost=round(c*NEW.delta/stock);bq=q;bc=c;status='estimated';coverage='complete';
  END IF;
 ELSE
  IF q>0 AND c>=0 THEN bq=q;bc=c;
   IF abs(NEW.delta)<=q THEN cost=-round(c*abs(NEW.delta)/q);
   ELSE cost=-(c+COALESCE(round(latest_c*(abs(NEW.delta)-q)/latest_q),0));fallback='negative_stock_latest_basis'; END IF;
  ELSE bq=COALESCE(latest_q,0);bc=COALESCE(latest_c,0);cost=-round(latest_c*abs(NEW.delta)/latest_q);fallback=CASE WHEN latest_q IS NULL THEN 'no_cost_history' ELSE 'negative_stock_latest_basis' END; END IF;
  IF stock>0 AND stock<>q THEN cost=NULL;fallback='partial_valuation'; END IF;
  IF cost IS NOT NULL THEN
   status='allocated';coverage='complete';
   IF fallback IS NULL AND warned>0 THEN fallback='unreliable_input'; END IF;
   IF fallback IS NULL AND stock<abs(NEW.delta) THEN fallback='insufficient_stock'; END IF;
   IF fallback IS NOT NULL THEN coverage='unreliable'; END IF;
  END IF;
 END IF;
 IF NEW.cost_total IS DISTINCT FROM cost OR NEW.cost_status IS DISTINCT FROM status OR NEW.cost_coverage IS DISTINCT FROM coverage
 OR NEW.cost_basis_quantity IS DISTINCT FROM bq OR NEW.cost_basis_total IS DISTINCT FROM bc OR NEW.cost_fallback IS DISTINCT FROM fallback
 OR NEW.cost_method IS DISTINCT FROM (CASE WHEN bq IS NOT NULL THEN 'moving_weighted_average' ELSE NULL END)
 OR (fallback IS NOT NULL AND NOT NEW.warnings @> jsonb_build_array(fallback))
 OR (stock+NEW.delta<0 AND NOT NEW.warnings @> '["negative_stock"]'::jsonb) THEN
  RAISE EXCEPTION 'Adjustment historical allocator basis, cost or warnings mismatch' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION inventory_adjustment_append_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE t inventory_transactions%ROWTYPE; a inventory_adjustments%ROWTYPE;
BEGIN
 SELECT * INTO t FROM inventory_transactions WHERE id=NEW.inventory_transaction_id;
 IF t.source_id LIKE 'inventory-adjustment:%' THEN
  SELECT * INTO a FROM inventory_adjustments WHERE 'inventory-adjustment:'||id::text=t.source_id FOR UPDATE;
 ELSIF t.source_id LIKE 'inventory-adjustment-reversal:%' THEN
  SELECT * INTO a FROM inventory_adjustments WHERE 'inventory-adjustment-reversal:'||reversal_of_id::text=t.source_id FOR UPDATE;
 END IF;
 IF (t.source_id LIKE 'inventory-adjustment:%' OR t.source_id LIKE 'inventory-adjustment-reversal:%') AND (a.id IS NULL OR a.creation_xid IS DISTINCT FROM pg_current_xact_id()) THEN
  RAISE EXCEPTION 'Cannot append to committed adjustment history' USING ERRCODE='55000';
 END IF;
 RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION inventory_adjustment_reconcile() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE a inventory_adjustments%ROWTYPE; t inventory_transactions%ROWTYPE; m inventory_stock_movements%ROWTYPE; l inventory_cost_layers%ROWTYPE; o inventory_adjustments%ROWTYPE; ol inventory_cost_layers%ROWTYPE;
BEGIN
 IF TG_TABLE_NAME='inventory_transactions' THEN
  IF NEW.source_id LIKE 'inventory-adjustment:%' OR NEW.source_id LIKE 'inventory-adjustment-reversal:%' THEN
   SELECT * INTO a FROM inventory_adjustments WHERE inventory_transaction_id=NEW.id;
   IF a.id IS NULL THEN RAISE EXCEPTION 'Semantic adjustment transaction requires its source' USING ERRCODE='23514'; END IF;
  ELSIF NEW.reversal_of_id IS NOT NULL AND EXISTS(SELECT 1 FROM inventory_adjustments WHERE inventory_transaction_id=NEW.reversal_of_id) THEN
   RAISE EXCEPTION 'Adjustment reversal requires its semantic source' USING ERRCODE='23514';
  ELSE RETURN NEW; END IF;
 ELSE SELECT * INTO a FROM inventory_adjustments WHERE id=NEW.id; END IF;
 SELECT * INTO t FROM inventory_transactions WHERE id=a.inventory_transaction_id;
 SELECT * INTO m FROM inventory_stock_movements WHERE inventory_transaction_id=t.id;
 SELECT * INTO l FROM inventory_cost_layers WHERE inventory_transaction_id=t.id;
 IF t.id IS NULL OR t.effective_date IS DISTINCT FROM a.effective_date OR t.created_by IS DISTINCT FROM a.created_by
 OR (SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=t.id)<>1
 OR m.inventory_item_id IS DISTINCT FROM a.inventory_item_id OR m.quantity IS DISTINCT FROM a.delta
 OR m.unit_id IS DISTINCT FROM (SELECT base_unit_id FROM inventory_items WHERE id=a.inventory_item_id)
 OR (SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=t.id)<>(CASE WHEN a.cost_total IS NULL THEN 0 ELSE 1 END)
 OR (a.cost_total IS NOT NULL AND (l.stock_movement_id IS DISTINCT FROM m.id OR l.total_cost IS DISTINCT FROM a.cost_total OR l.valuation_warning IS DISTINCT FROM a.cost_fallback)) THEN
  RAISE EXCEPTION 'Adjustment transaction, movement and cost must reconcile' USING ERRCODE='23514';
 END IF;
 IF a.reversal_of_id IS NULL THEN
  IF t.type<>'adjustment' OR t.source_type<>'adjustment' OR t.source_id IS DISTINCT FROM 'inventory-adjustment:'||a.id::text OR t.reversal_of_id IS NOT NULL OR l.reversal_of_id IS NOT NULL THEN
   RAISE EXCEPTION 'Adjustment original provenance mismatch' USING ERRCODE='23514'; END IF;
 ELSE
  SELECT * INTO o FROM inventory_adjustments WHERE id=a.reversal_of_id;
  SELECT * INTO ol FROM inventory_cost_layers WHERE inventory_transaction_id=o.inventory_transaction_id;
  IF t.type<>'reversal' OR t.source_type<>'reversal' OR t.source_id IS DISTINCT FROM 'inventory-adjustment-reversal:'||o.id::text
   OR t.reversal_of_id IS DISTINCT FROM o.inventory_transaction_id OR l.reversal_of_id IS DISTINCT FROM ol.id THEN
   RAISE EXCEPTION 'Adjustment exact reversal linkage mismatch' USING ERRCODE='23514'; END IF;
 END IF;
 RETURN NEW;
END; $$;
CREATE TRIGGER inventory_adjustment_history_guard BEFORE INSERT OR UPDATE OR DELETE ON inventory_adjustments FOR EACH ROW EXECUTE FUNCTION inventory_adjustment_guard();
CREATE TRIGGER inventory_adjustment_movements_append BEFORE INSERT ON inventory_stock_movements FOR EACH ROW EXECUTE FUNCTION inventory_adjustment_append_guard();
CREATE TRIGGER inventory_adjustment_layers_append BEFORE INSERT ON inventory_cost_layers FOR EACH ROW EXECUTE FUNCTION inventory_adjustment_append_guard();
CREATE CONSTRAINT TRIGGER inventory_adjustment_reconcile AFTER INSERT OR UPDATE ON inventory_adjustments DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_adjustment_reconcile();
CREATE CONSTRAINT TRIGGER inventory_adjustment_transaction_source AFTER INSERT ON inventory_transactions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION inventory_adjustment_reconcile();
SQL);
        $this->installCostLayerValidator(true);
    }

    public function down(): void
    {
        if (DB::table('inventory_adjustments')->exists()) {
            throw new RuntimeException('Adjustment rollback requires an empty aggregate boundary.');
        }
        $this->installCostLayerValidator(false);
        DB::unprepared('DROP TRIGGER inventory_adjustment_movements_append ON inventory_stock_movements; DROP TRIGGER inventory_adjustment_layers_append ON inventory_cost_layers; DROP TRIGGER inventory_adjustment_transaction_source ON inventory_transactions;');
        Schema::drop('inventory_adjustments');
        foreach (['inventory_adjustment_guard', 'inventory_adjustment_append_guard', 'inventory_adjustment_reconcile'] as $fn) {
            DB::statement("DROP FUNCTION {$fn}()");
        }
    }

    private function installCostLayerValidator(bool $adjustments): void
    {
        // Same AOB-3 validator with only the semantic adjustment consumption gate extended.
        $condition = $adjustments
            ? "(NEW.quantity<0 AND t.type<>'production' AND NOT (t.type='adjustment' AND t.source_type='adjustment' AND EXISTS(SELECT 1 FROM inventory_adjustments WHERE 'inventory-adjustment:'||id::text=t.source_id AND reversal_of_id IS NULL)))"
            : "(NEW.quantity<0 AND t.type<>'production')";
        DB::unprepared(<<<SQL
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
END; $$;
SQL);
    }
};
