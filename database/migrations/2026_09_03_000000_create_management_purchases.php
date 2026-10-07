<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_cost_layers', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')->nullable()->unique()
                ->constrained('inventory_cost_layers')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE inventory_cost_layers DROP CONSTRAINT inventory_cost_layers_quantity_positive');
        DB::statement('ALTER TABLE inventory_cost_layers DROP CONSTRAINT inventory_cost_layers_total_cost_nonnegative');
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_cost_layers
            ADD CONSTRAINT inventory_cost_layers_reversal_shape_valid CHECK (
                (reversal_of_id IS NULL AND quantity > 0 AND total_cost >= 0)
                OR (reversal_of_id IS NOT NULL AND quantity < 0 AND total_cost <= 0)
            )
            SQL);

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('supplier_name', 160)->nullable();
            $table->string('description', 240)->nullable();
            $table->date('effective_date');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('total');
            $table->foreignId('financial_transaction_id')->unique()->constrained('financial_transactions')->restrictOnDelete();
            $table->foreignId('inventory_transaction_id')->nullable()->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['effective_date', 'id'], 'purchases_history_index');
            $table->index(['wallet_id', 'effective_date'], 'purchases_wallet_period_index');
        });

        // Full top-level transaction identity is immutable; avoid 32-bit xmin/savepoint ambiguity.
        DB::statement('ALTER TABLE purchases ADD COLUMN creation_xid xid8 NOT NULL DEFAULT pg_current_xact_id()');

        Schema::create('purchase_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('purchase_presentation_id')->nullable()->constrained('inventory_purchase_presentations')->restrictOnDelete();
            $table->foreignId('input_unit_id')->nullable()->constrained('inventory_units')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->decimal('base_quantity', 20, 6);
            $table->string('price_mode', 16);
            $table->unsignedBigInteger('price_amount');
            $table->unsignedBigInteger('line_total');
            $table->string('item_name', 160);
            $table->string('category_name', 120);
            $table->boolean('tracks_stock');
            $table->string('base_unit_code', 24);
            $table->string('input_unit_code', 24)->nullable();
            $table->decimal('input_unit_factor', 20, 9)->nullable();
            $table->string('presentation_name', 120)->nullable();
            $table->decimal('presentation_content_quantity', 20, 6)->nullable();
            $table->string('presentation_unit_code', 24)->nullable();
            $table->decimal('presentation_unit_factor', 20, 9)->nullable();
            $table->foreignId('stock_movement_id')->nullable()->unique()->constrained('inventory_stock_movements')->restrictOnDelete();
            $table->foreignId('inventory_cost_layer_id')->nullable()->unique()->constrained('inventory_cost_layers')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['purchase_id', 'inventory_item_id'], 'purchase_lines_purchase_item_unique');
        });

        Schema::create('purchase_reversals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->unique()->constrained('purchases')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->unique()->constrained('financial_transactions')->restrictOnDelete();
            $table->foreignId('inventory_transaction_id')->nullable()->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $table->string('description', 240);
            $table->date('effective_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_type_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions
            ADD CONSTRAINT financial_transactions_type_valid CHECK (
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense',
                         'management_outflow', 'transfer', 'distribution', 'order_payment', 'purchase', 'reversal')
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE purchases
            ADD CONSTRAINT purchases_amounts_valid CHECK (
                subtotal >= 0 AND shipping_amount >= 0 AND total > 0 AND total = subtotal + shipping_amount
            ),
            ADD CONSTRAINT purchases_supplier_valid CHECK (supplier_name IS NULL OR btrim(supplier_name) <> ''),
            ADD CONSTRAINT purchases_description_valid CHECK (description IS NULL OR btrim(description) <> ''),
            ADD CONSTRAINT purchases_fingerprint_valid CHECK (request_fingerprint ~ '^[0-9a-f]{64}$')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE purchase_lines
            ADD CONSTRAINT purchase_lines_values_valid CHECK (
                quantity > 0 AND base_quantity > 0 AND line_total >= 0 AND price_amount >= 0
                AND price_mode IN ('total', 'unit')
            ),
            ADD CONSTRAINT purchase_lines_input_shape_valid CHECK (
                (purchase_presentation_id IS NULL) <> (input_unit_id IS NULL)
                AND ((input_unit_id IS NOT NULL AND input_unit_code IS NOT NULL AND input_unit_factor IS NOT NULL
                      AND presentation_name IS NULL AND presentation_content_quantity IS NULL
                      AND presentation_unit_code IS NULL AND presentation_unit_factor IS NULL)
                  OR (purchase_presentation_id IS NOT NULL AND input_unit_code IS NULL AND input_unit_factor IS NULL
                      AND presentation_name IS NOT NULL AND presentation_content_quantity IS NOT NULL
                      AND presentation_unit_code IS NOT NULL AND presentation_unit_factor IS NOT NULL))
            ),
            ADD CONSTRAINT purchase_lines_inventory_shape_valid CHECK (
                (tracks_stock AND stock_movement_id IS NOT NULL AND inventory_cost_layer_id IS NOT NULL)
                OR (NOT tracks_stock AND stock_movement_id IS NULL AND inventory_cost_layer_id IS NULL)
            )
            SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION inventory_validate_cost_layer() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE m inventory_stock_movements%ROWTYPE; t inventory_transactions%ROWTYPE; o inventory_cost_layers%ROWTYPE;
BEGIN
 SELECT * INTO m FROM inventory_stock_movements WHERE id=NEW.stock_movement_id;
 SELECT * INTO t FROM inventory_transactions WHERE id=NEW.inventory_transaction_id;
 IF m.id IS NULL OR NEW.inventory_item_id IS DISTINCT FROM m.inventory_item_id
 OR NEW.inventory_transaction_id IS DISTINCT FROM m.inventory_transaction_id
 OR NEW.quantity IS DISTINCT FROM m.quantity OR NEW.effective_date IS DISTINCT FROM t.effective_date THEN
  RAISE EXCEPTION 'Cost layer must exactly match its source movement' USING ERRCODE='23514';
 END IF;
 IF NEW.reversal_of_id IS NULL THEN
  IF NEW.quantity <= 0 OR NEW.total_cost < 0 OR t.type='reversal' THEN
   RAISE EXCEPTION 'Original cost layer requires a positive receipt' USING ERRCODE='23514';
  END IF;
 ELSE
  SELECT * INTO o FROM inventory_cost_layers WHERE id=NEW.reversal_of_id;
  IF o.id IS NULL OR o.reversal_of_id IS NOT NULL OR t.type <> 'reversal'
  OR t.reversal_of_id IS DISTINCT FROM o.inventory_transaction_id
  OR NEW.inventory_item_id IS DISTINCT FROM o.inventory_item_id
  OR NEW.quantity IS DISTINCT FROM -o.quantity OR NEW.total_cost IS DISTINCT FROM -o.total_cost THEN
   RAISE EXCEPTION 'Cost reversal must exactly compensate the original transaction layer' USING ERRCODE='23514';
  END IF;
 END IF;
 RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION management_validate_purchase_line() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE p purchases%ROWTYPE; i inventory_items%ROWTYPE; u inventory_units%ROWTYPE; pr inventory_purchase_presentations%ROWTYPE; c inventory_categories%ROWTYPE; base numeric;
BEGIN
 SELECT * INTO p FROM purchases WHERE id=NEW.purchase_id;
 IF p.creation_xid IS DISTINCT FROM pg_current_xact_id() THEN
  RAISE EXCEPTION 'Cannot append lines to committed purchase history' USING ERRCODE='55000';
 END IF;
 SELECT * INTO i FROM inventory_items WHERE id=NEW.inventory_item_id FOR UPDATE;
 SELECT * INTO c FROM inventory_categories WHERE id=i.inventory_category_id;
 SELECT * INTO u FROM inventory_units WHERE id=i.base_unit_id;
 IF NEW.item_name IS DISTINCT FROM i.name OR NEW.category_name IS DISTINCT FROM c.name
 OR NEW.tracks_stock IS DISTINCT FROM i.tracks_stock OR NEW.base_unit_code IS DISTINCT FROM u.code THEN
  RAISE EXCEPTION 'Purchase item snapshot does not match locked identity' USING ERRCODE='23514';
 END IF;
 IF NEW.input_unit_id IS NOT NULL THEN
  SELECT * INTO u FROM inventory_units WHERE id=NEW.input_unit_id;
  IF u.family IS DISTINCT FROM i.unit_family OR NEW.input_unit_code IS DISTINCT FROM u.code OR NEW.input_unit_factor IS DISTINCT FROM u.factor_to_base THEN
   RAISE EXCEPTION 'Purchase input snapshot does not match unit' USING ERRCODE='23514';
  END IF;
  base:=NEW.quantity*u.factor_to_base;
 ELSE
  SELECT * INTO pr FROM inventory_purchase_presentations WHERE id=NEW.purchase_presentation_id;
  SELECT * INTO u FROM inventory_units WHERE id=pr.content_unit_id;
  IF pr.inventory_item_id IS DISTINCT FROM i.id OR NEW.presentation_name IS DISTINCT FROM pr.name
  OR NEW.presentation_content_quantity IS DISTINCT FROM pr.content_quantity
  OR NEW.presentation_unit_code IS DISTINCT FROM u.code OR NEW.presentation_unit_factor IS DISTINCT FROM u.factor_to_base THEN
   RAISE EXCEPTION 'Purchase presentation snapshot does not match source' USING ERRCODE='23514';
  END IF;
  base:=NEW.quantity*pr.content_quantity*u.factor_to_base;
 END IF;
 IF NEW.base_quantity IS DISTINCT FROM base OR trunc(base,i.quantity_precision)<>base
 OR NEW.line_total IS DISTINCT FROM (CASE WHEN NEW.price_mode='unit' THEN NEW.price_amount*NEW.quantity ELSE NEW.price_amount END) THEN
  RAISE EXCEPTION 'Purchase exact normalization or line price is invalid' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION management_assert_purchase(pid bigint) RETURNS void LANGUAGE plpgsql AS $$
DECLARE p purchases%ROWTYPE; f financial_transactions%ROWTYPE; inv inventory_transactions%ROWTYPE; n bigint; stocked bigint; subtotal numeric;
BEGIN
 SELECT * INTO p FROM purchases WHERE id=pid;
 SELECT count(*),count(*) FILTER(WHERE tracks_stock),coalesce(sum(line_total),0) INTO n,stocked,subtotal FROM purchase_lines WHERE purchase_id=pid;
 IF n=0 OR subtotal<>p.subtotal THEN RAISE EXCEPTION 'Purchase lines do not reconcile with subtotal' USING ERRCODE='23514'; END IF;
 SELECT * INTO f FROM financial_transactions WHERE id=p.financial_transaction_id;
 IF f.type IS DISTINCT FROM 'purchase' OR f.effective_date IS DISTINCT FROM p.effective_date OR f.created_by IS DISTINCT FROM p.created_by
 OR (SELECT count(*) FROM ledger_entries WHERE financial_transaction_id=f.id)<>1
 OR NOT EXISTS(SELECT 1 FROM ledger_entries WHERE financial_transaction_id=f.id AND wallet_id=p.wallet_id AND amount=-p.total) THEN
  RAISE EXCEPTION 'Purchase financial impact does not reconcile' USING ERRCODE='23514';
 END IF;
 IF (stocked=0) IS DISTINCT FROM (p.inventory_transaction_id IS NULL) THEN
  RAISE EXCEPTION 'Purchase inventory transaction shape is invalid' USING ERRCODE='23514';
 END IF;
 IF stocked>0 THEN
  SELECT * INTO inv FROM inventory_transactions WHERE id=p.inventory_transaction_id;
  IF inv.type IS DISTINCT FROM 'purchase_receipt' OR inv.source_type IS DISTINCT FROM 'purchase'
  OR inv.source_id IS DISTINCT FROM p.idempotency_key::text OR inv.effective_date IS DISTINCT FROM p.effective_date OR inv.created_by IS DISTINCT FROM p.created_by
  OR (SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=inv.id)<>stocked
  OR (SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=inv.id)<>stocked
  OR EXISTS(SELECT 1 FROM purchase_lines l
   LEFT JOIN inventory_stock_movements m ON m.id=l.stock_movement_id
   LEFT JOIN inventory_cost_layers cl ON cl.id=l.inventory_cost_layer_id
   WHERE l.purchase_id=pid AND l.tracks_stock AND
   (m.inventory_transaction_id IS DISTINCT FROM inv.id OR m.inventory_item_id IS DISTINCT FROM l.inventory_item_id
    OR m.quantity IS DISTINCT FROM l.base_quantity OR cl.stock_movement_id IS DISTINCT FROM m.id OR cl.quantity IS DISTINCT FROM l.base_quantity OR cl.total_cost IS DISTINCT FROM l.line_total)) THEN
   RAISE EXCEPTION 'Purchase physical impact does not reconcile' USING ERRCODE='23514';
  END IF;
 END IF;
END; $$;

CREATE OR REPLACE FUNCTION management_purchase_reconcile() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE pid bigint;
BEGIN
 IF TG_TABLE_NAME='purchases' THEN pid:=NEW.id;
 ELSIF TG_TABLE_NAME='ledger_entries' THEN SELECT id INTO pid FROM purchases WHERE financial_transaction_id=NEW.financial_transaction_id;
 ELSE SELECT id INTO pid FROM purchases WHERE inventory_transaction_id=NEW.inventory_transaction_id;
 END IF;
 IF pid IS NOT NULL THEN PERFORM management_assert_purchase(pid); END IF;
 RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION management_assert_purchase_reversal(rid bigint) RETURNS void LANGUAGE plpgsql AS $$
DECLARE r purchase_reversals%ROWTYPE; p purchases%ROWTYPE; f financial_transactions%ROWTYPE; inv inventory_transactions%ROWTYPE; n bigint;
BEGIN
 SELECT * INTO r FROM purchase_reversals WHERE id=rid;
 SELECT * INTO p FROM purchases WHERE id=r.purchase_id;
 SELECT * INTO f FROM financial_transactions WHERE id=r.financial_transaction_id;
 IF f.type IS DISTINCT FROM 'reversal' OR f.reversal_of_id IS DISTINCT FROM p.financial_transaction_id
 OR f.effective_date IS DISTINCT FROM r.effective_date OR f.created_by IS DISTINCT FROM r.created_by
 OR (SELECT count(*) FROM ledger_entries WHERE financial_transaction_id=f.id)<>1
 OR NOT EXISTS(SELECT 1 FROM ledger_entries WHERE financial_transaction_id=f.id AND wallet_id=p.wallet_id AND amount=p.total) THEN
  RAISE EXCEPTION 'Purchase financial reversal does not reconcile' USING ERRCODE='23514';
 END IF;
 IF (p.inventory_transaction_id IS NULL) IS DISTINCT FROM (r.inventory_transaction_id IS NULL) THEN
  RAISE EXCEPTION 'Purchase inventory reversal shape is invalid' USING ERRCODE='23514';
 END IF;
 IF r.inventory_transaction_id IS NOT NULL THEN
  SELECT * INTO inv FROM inventory_transactions WHERE id=r.inventory_transaction_id;
  SELECT count(*) INTO n FROM purchase_lines WHERE purchase_id=p.id AND tracks_stock;
  IF inv.type IS DISTINCT FROM 'reversal' OR inv.reversal_of_id IS DISTINCT FROM p.inventory_transaction_id
  OR inv.effective_date IS DISTINCT FROM r.effective_date OR inv.created_by IS DISTINCT FROM r.created_by
  OR (SELECT count(*) FROM inventory_stock_movements WHERE inventory_transaction_id=inv.id)<>n
  OR (SELECT count(*) FROM inventory_cost_layers WHERE inventory_transaction_id=inv.id)<>n
  OR EXISTS(SELECT 1 FROM purchase_lines l
    LEFT JOIN inventory_stock_movements m ON m.inventory_transaction_id=inv.id AND m.inventory_item_id=l.inventory_item_id
    LEFT JOIN inventory_cost_layers cl ON cl.stock_movement_id=m.id
    WHERE l.purchase_id=p.id AND l.tracks_stock AND
      (m.quantity IS DISTINCT FROM -l.base_quantity OR cl.reversal_of_id IS DISTINCT FROM l.inventory_cost_layer_id OR cl.total_cost IS DISTINCT FROM -l.line_total)) THEN
   RAISE EXCEPTION 'Purchase physical reversal does not reconcile' USING ERRCODE='23514';
  END IF;
 END IF;
END; $$;

CREATE OR REPLACE FUNCTION management_purchase_reversal_reconcile() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE rid bigint;
BEGIN
 IF TG_TABLE_NAME='purchase_reversals' THEN rid:=NEW.id;
 ELSIF TG_TABLE_NAME='ledger_entries' THEN SELECT id INTO rid FROM purchase_reversals WHERE financial_transaction_id=NEW.financial_transaction_id;
 ELSE SELECT id INTO rid FROM purchase_reversals WHERE inventory_transaction_id=NEW.inventory_transaction_id;
 END IF;
 IF rid IS NOT NULL THEN PERFORM management_assert_purchase_reversal(rid); END IF;
 RETURN NEW;
END; $$;

CREATE OR REPLACE FUNCTION management_require_purchase_source() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.type='purchase' AND NOT EXISTS(SELECT 1 FROM purchases WHERE financial_transaction_id=NEW.id) THEN
  RAISE EXCEPTION 'Purchase financial transaction requires its Purchase source' USING ERRCODE='23514';
 END IF;
 IF NEW.type='reversal' AND EXISTS(SELECT 1 FROM purchases WHERE financial_transaction_id=NEW.reversal_of_id)
 AND NOT EXISTS(SELECT 1 FROM purchase_reversals WHERE financial_transaction_id=NEW.id) THEN
  RAISE EXCEPTION 'Purchase reversal requires coordinated Purchase source' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END; $$;
CREATE OR REPLACE FUNCTION management_require_inventory_purchase_source() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.type='purchase_receipt' AND NOT EXISTS(SELECT 1 FROM purchases WHERE inventory_transaction_id=NEW.id) THEN
  RAISE EXCEPTION 'Purchase receipt requires its Purchase source' USING ERRCODE='23514';
 END IF;
 IF NEW.type='reversal' AND EXISTS(SELECT 1 FROM purchases WHERE inventory_transaction_id=NEW.reversal_of_id)
 AND NOT EXISTS(SELECT 1 FROM purchase_reversals WHERE inventory_transaction_id=NEW.id) THEN
  RAISE EXCEPTION 'Purchase inventory reversal requires coordinated source' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END; $$;
CREATE OR REPLACE FUNCTION management_prevent_purchase_history_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Purchase history is immutable' USING ERRCODE='55000'; END; $$;

CREATE TRIGGER purchase_lines_validate BEFORE INSERT ON purchase_lines FOR EACH ROW EXECUTE FUNCTION management_validate_purchase_line();
CREATE CONSTRAINT TRIGGER purchases_reconcile AFTER INSERT ON purchases DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION management_purchase_reconcile();
CREATE CONSTRAINT TRIGGER purchase_reversals_reconcile AFTER INSERT ON purchase_reversals DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION management_purchase_reversal_reconcile();
CREATE CONSTRAINT TRIGGER financial_purchase_requires_source AFTER INSERT ON financial_transactions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION management_require_purchase_source();
CREATE CONSTRAINT TRIGGER inventory_purchase_requires_source AFTER INSERT ON inventory_transactions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION management_require_inventory_purchase_source();
CREATE TRIGGER purchases_immutable BEFORE UPDATE OR DELETE ON purchases FOR EACH ROW EXECUTE FUNCTION management_prevent_purchase_history_mutation();
CREATE TRIGGER purchase_lines_immutable BEFORE UPDATE OR DELETE ON purchase_lines FOR EACH ROW EXECUTE FUNCTION management_prevent_purchase_history_mutation();
CREATE TRIGGER purchase_reversals_immutable BEFORE UPDATE OR DELETE ON purchase_reversals FOR EACH ROW EXECUTE FUNCTION management_prevent_purchase_history_mutation();
SQL);
        foreach (['ledger_entries', 'inventory_stock_movements', 'inventory_cost_layers'] as $table) {
            DB::unprepared("CREATE CONSTRAINT TRIGGER {$table}_purchase_reconcile AFTER INSERT ON {$table} DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION management_purchase_reconcile();
                CREATE CONSTRAINT TRIGGER {$table}_purchase_reversal_reconcile AFTER INSERT ON {$table} DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION management_purchase_reversal_reconcile();");
        }
    }

    public function down(): void
    {
        foreach (['ledger_entries', 'inventory_stock_movements', 'inventory_cost_layers'] as $table) {
            DB::statement("DROP TRIGGER {$table}_purchase_reconcile ON {$table}");
            DB::statement("DROP TRIGGER {$table}_purchase_reversal_reconcile ON {$table}");
        }
        DB::statement('DROP TRIGGER financial_purchase_requires_source ON financial_transactions');
        DB::statement('DROP TRIGGER inventory_purchase_requires_source ON inventory_transactions');
        Schema::drop('purchase_reversals');
        Schema::drop('purchase_lines');
        Schema::drop('purchases');
        foreach (['management_validate_purchase_line()', 'management_purchase_reconcile()', 'management_purchase_reversal_reconcile()', 'management_assert_purchase(bigint)', 'management_assert_purchase_reversal(bigint)', 'management_require_purchase_source()', 'management_require_inventory_purchase_source()', 'management_prevent_purchase_history_mutation()'] as $function) {
            DB::statement('DROP FUNCTION '.$function);
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION inventory_validate_cost_layer() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE m inventory_stock_movements%ROWTYPE; t inventory_transactions%ROWTYPE;
BEGIN
 SELECT * INTO m FROM inventory_stock_movements WHERE id=NEW.stock_movement_id;
 SELECT * INTO t FROM inventory_transactions WHERE id=NEW.inventory_transaction_id;
 IF m.id IS NULL OR m.quantity<=0 OR NEW.inventory_item_id IS DISTINCT FROM m.inventory_item_id
 OR NEW.inventory_transaction_id IS DISTINCT FROM m.inventory_transaction_id OR NEW.quantity IS DISTINCT FROM m.quantity
 OR NEW.effective_date IS DISTINCT FROM t.effective_date THEN
  RAISE EXCEPTION 'Cost layer must exactly match a positive source movement' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END; $$;
SQL);
        DB::statement('ALTER TABLE inventory_cost_layers DROP CONSTRAINT inventory_cost_layers_reversal_shape_valid');
        Schema::table('inventory_cost_layers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversal_of_id');
        });
        DB::statement('ALTER TABLE inventory_cost_layers ADD CONSTRAINT inventory_cost_layers_quantity_positive CHECK (quantity > 0), ADD CONSTRAINT inventory_cost_layers_total_cost_nonnegative CHECK (total_cost >= 0)');
        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_type_valid');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_type_valid CHECK (type IN ('opening','adjustment_increase','adjustment_decrease','manual_income','manual_expense','management_outflow','transfer','distribution','order_payment','reversal'))");
    }
};
