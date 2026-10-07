<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('inventory_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('name', 80);
            $table->string('symbol', 24);
            $table->string('family', 16);
            $table->decimal('factor_to_base', 20, 9);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_system')->default(true);
            $table->timestampsTz();
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->string('name', 160);
            $table->boolean('tracks_stock')->default(true);
            $table->string('unit_family', 16);
            $table->foreignId('base_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->foreignId('default_input_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity_precision')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['is_active', 'name'], 'inventory_items_admin_list_index');
        });

        Schema::create('inventory_purchase_presentations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('name', 120);
            $table->decimal('content_quantity', 20, 6);
            $table->foreignId('content_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['inventory_item_id', 'is_active'], 'inventory_presentations_item_active_index');
        });

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->date('effective_date');
            $table->string('source_type', 64)->nullable();
            $table->string('source_id', 128)->nullable();
            $table->string('description', 240)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('inventory_transactions')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique('reversal_of_id', 'inventory_transactions_single_reversal_unique');
            $table->unique(['source_type', 'source_id'], 'inventory_transactions_source_unique');
            $table->index(['effective_date', 'id'], 'inventory_transactions_history_index');
        });

        Schema::create('inventory_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_transaction_id')->constrained('inventory_transactions')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['inventory_item_id', 'inventory_transaction_id'], 'inventory_movements_item_history_index');
        });

        Schema::create('inventory_cost_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('inventory_transaction_id')->constrained('inventory_transactions')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->unique()->constrained('inventory_stock_movements')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->unsignedBigInteger('total_cost');
            $table->date('effective_date');
            $table->timestampsTz();

            $table->index(['inventory_item_id', 'effective_date', 'id'], 'inventory_cost_layers_item_history_index');
        });

        DB::statement('CREATE UNIQUE INDEX inventory_categories_name_unique ON inventory_categories (lower(name))');
        DB::statement('CREATE UNIQUE INDEX inventory_items_name_unique ON inventory_items (lower(name))');
        DB::statement('CREATE UNIQUE INDEX inventory_presentations_item_name_unique ON inventory_purchase_presentations (inventory_item_id, lower(name))');
        DB::statement('CREATE UNIQUE INDEX inventory_units_single_base_per_family_unique ON inventory_units (family) WHERE is_base');

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_categories
            ADD CONSTRAINT inventory_categories_name_valid CHECK (btrim(name) <> '')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_units
            ADD CONSTRAINT inventory_units_name_valid CHECK (btrim(name) <> '' AND btrim(symbol) <> ''),
            ADD CONSTRAINT inventory_units_family_valid CHECK (family IN ('weight', 'volume', 'count')),
            ADD CONSTRAINT inventory_units_factor_positive CHECK (factor_to_base > 0),
            ADD CONSTRAINT inventory_units_base_shape_valid CHECK (NOT is_base OR factor_to_base = 1)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_items
            ADD CONSTRAINT inventory_items_name_valid CHECK (btrim(name) <> ''),
            ADD CONSTRAINT inventory_items_family_valid CHECK (unit_family IN ('weight', 'volume', 'count')),
            ADD CONSTRAINT inventory_items_quantity_precision_valid CHECK (quantity_precision BETWEEN 0 AND 6)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_purchase_presentations
            ADD CONSTRAINT inventory_presentations_name_valid CHECK (btrim(name) <> ''),
            ADD CONSTRAINT inventory_presentations_content_positive CHECK (content_quantity > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_transactions
            ADD CONSTRAINT inventory_transactions_type_valid CHECK (
                type IN ('purchase_receipt', 'adjustment', 'production', 'production_consumption', 'sale_consumption', 'reversal')
            ),
            ADD CONSTRAINT inventory_transactions_source_shape_valid CHECK (
                (source_type IS NULL) = (source_id IS NULL)
                AND (source_type IS NULL OR (btrim(source_type) <> '' AND btrim(source_id) <> ''))
            ),
            ADD CONSTRAINT inventory_transactions_source_type_valid CHECK (
                source_type IS NULL OR source_type IN ('purchase', 'production', 'order', 'adjustment', 'reversal')
            ),
            ADD CONSTRAINT inventory_transactions_type_source_valid CHECK (
                source_type IS NOT NULL AND (
                    (type = 'purchase_receipt' AND source_type = 'purchase')
                    OR (type = 'adjustment' AND source_type = 'adjustment')
                    OR (type IN ('production', 'production_consumption') AND source_type = 'production')
                    OR (type = 'sale_consumption' AND source_type = 'order')
                    OR (type = 'reversal' AND source_type = 'reversal')
                )
            ),
            ADD CONSTRAINT inventory_transactions_reversal_shape_valid CHECK (
                (type = 'reversal') = (reversal_of_id IS NOT NULL)
                AND (reversal_of_id IS NULL OR reversal_of_id <> id)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_stock_movements
            ADD CONSTRAINT inventory_stock_movements_quantity_nonzero CHECK (quantity <> 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_cost_layers
            ADD CONSTRAINT inventory_cost_layers_quantity_positive CHECK (quantity > 0),
            ADD CONSTRAINT inventory_cost_layers_total_cost_nonnegative CHECK (total_cost >= 0)
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION inventory_prevent_history_mutation()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Inventory history is immutable' USING ERRCODE = '55000';
            END;
            $$;

            CREATE OR REPLACE FUNCTION inventory_prevent_system_unit_mutation()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.is_system THEN
                    RAISE EXCEPTION 'System inventory units are immutable' USING ERRCODE = '55000';
                END IF;
                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION inventory_validate_item_units()
            RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE base_record inventory_units%ROWTYPE;
            DECLARE input_record inventory_units%ROWTYPE;
            BEGIN
                IF TG_OP = 'UPDATE' AND (
                    (
                        EXISTS (SELECT 1 FROM inventory_stock_movements WHERE inventory_item_id = OLD.id)
                        AND (
                            NEW.tracks_stock IS DISTINCT FROM OLD.tracks_stock
                            OR NEW.unit_family IS DISTINCT FROM OLD.unit_family
                            OR NEW.base_unit_id IS DISTINCT FROM OLD.base_unit_id
                            OR NEW.quantity_precision IS DISTINCT FROM OLD.quantity_precision
                        )
                    ) OR (
                        EXISTS (SELECT 1 FROM inventory_purchase_presentations WHERE inventory_item_id = OLD.id)
                        AND (
                            NEW.unit_family IS DISTINCT FROM OLD.unit_family
                            OR NEW.base_unit_id IS DISTINCT FROM OLD.base_unit_id
                            OR NEW.quantity_precision IS DISTINCT FROM OLD.quantity_precision
                        )
                    )
                ) THEN
                    RAISE EXCEPTION 'Item measurement identity is immutable after dependent inventory records exist' USING ERRCODE = '55000';
                END IF;
                SELECT * INTO base_record FROM inventory_units WHERE id = NEW.base_unit_id;
                SELECT * INTO input_record FROM inventory_units WHERE id = NEW.default_input_unit_id;
                IF base_record.id IS NULL OR NOT base_record.is_base OR base_record.family <> NEW.unit_family THEN
                    RAISE EXCEPTION 'Inventory item base unit must be the base of its family' USING ERRCODE = '23514';
                END IF;
                IF input_record.id IS NULL OR input_record.family <> NEW.unit_family THEN
                    RAISE EXCEPTION 'Inventory item input unit must belong to its family' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION inventory_validate_presentation()
            RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE item_record inventory_items%ROWTYPE;
            DECLARE unit_record inventory_units%ROWTYPE;
            DECLARE base_quantity numeric;
            BEGIN
                SELECT * INTO item_record FROM inventory_items WHERE id = NEW.inventory_item_id FOR UPDATE;
                SELECT * INTO unit_record FROM inventory_units WHERE id = NEW.content_unit_id;
                IF unit_record.id IS NULL OR unit_record.family <> item_record.unit_family THEN
                    RAISE EXCEPTION 'Presentation unit must belong to the item family' USING ERRCODE = '23514';
                END IF;
                base_quantity := NEW.content_quantity * unit_record.factor_to_base;
                IF base_quantity <> round(base_quantity, item_record.quantity_precision) THEN
                    RAISE EXCEPTION 'Presentation content exceeds item quantity precision' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION inventory_validate_stock_movement()
            RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE item_record inventory_items%ROWTYPE;
            BEGIN
                -- Serialize direct movement inserts with parent identity changes, not only action writes.
                SELECT * INTO item_record FROM inventory_items WHERE id = NEW.inventory_item_id FOR UPDATE;
                IF NOT item_record.tracks_stock THEN
                    RAISE EXCEPTION 'Non-stockable item cannot receive stock movements' USING ERRCODE = '23514';
                END IF;
                IF NEW.unit_id <> item_record.base_unit_id THEN
                    RAISE EXCEPTION 'Stock movements must use the item base unit' USING ERRCODE = '23514';
                END IF;
                IF NEW.quantity <> round(NEW.quantity, item_record.quantity_precision) THEN
                    RAISE EXCEPTION 'Stock movement exceeds item quantity precision' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION inventory_validate_cost_layer()
            RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE movement_record inventory_stock_movements%ROWTYPE;
            DECLARE transaction_date date;
            BEGIN
                SELECT * INTO movement_record FROM inventory_stock_movements WHERE id = NEW.stock_movement_id;
                SELECT effective_date INTO transaction_date FROM inventory_transactions WHERE id = NEW.inventory_transaction_id;
                IF movement_record.id IS NULL OR movement_record.quantity <= 0
                    OR NEW.inventory_item_id <> movement_record.inventory_item_id
                    OR NEW.inventory_transaction_id <> movement_record.inventory_transaction_id
                    OR NEW.quantity <> movement_record.quantity
                    OR NEW.effective_date <> transaction_date THEN
                    RAISE EXCEPTION 'Cost layer must exactly match its positive source movement' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION inventory_require_stock_movements()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM inventory_stock_movements WHERE inventory_transaction_id = NEW.id) THEN
                    RAISE EXCEPTION 'Inventory transaction % requires at least one stock movement', NEW.id USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER inventory_units_system_immutable
            BEFORE UPDATE OR DELETE ON inventory_units
            FOR EACH ROW EXECUTE FUNCTION inventory_prevent_system_unit_mutation();

            CREATE TRIGGER inventory_items_units_valid
            BEFORE INSERT OR UPDATE ON inventory_items
            FOR EACH ROW EXECUTE FUNCTION inventory_validate_item_units();

            CREATE TRIGGER inventory_presentations_valid
            BEFORE INSERT OR UPDATE ON inventory_purchase_presentations
            FOR EACH ROW EXECUTE FUNCTION inventory_validate_presentation();

            CREATE TRIGGER inventory_transactions_immutable
            BEFORE UPDATE OR DELETE ON inventory_transactions
            FOR EACH ROW EXECUTE FUNCTION inventory_prevent_history_mutation();

            CREATE TRIGGER inventory_stock_movements_valid
            BEFORE INSERT ON inventory_stock_movements
            FOR EACH ROW EXECUTE FUNCTION inventory_validate_stock_movement();

            CREATE TRIGGER inventory_stock_movements_immutable
            BEFORE UPDATE OR DELETE ON inventory_stock_movements
            FOR EACH ROW EXECUTE FUNCTION inventory_prevent_history_mutation();

            CREATE TRIGGER inventory_cost_layers_valid
            BEFORE INSERT ON inventory_cost_layers
            FOR EACH ROW EXECUTE FUNCTION inventory_validate_cost_layer();

            CREATE TRIGGER inventory_cost_layers_immutable
            BEFORE UPDATE OR DELETE ON inventory_cost_layers
            FOR EACH ROW EXECUTE FUNCTION inventory_prevent_history_mutation();

            CREATE CONSTRAINT TRIGGER inventory_transactions_require_movements
            AFTER INSERT ON inventory_transactions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION inventory_require_stock_movements();
            SQL);

        DB::table('inventory_units')->insert([
            ['code' => 'g', 'name' => 'Gram', 'symbol' => 'g', 'family' => 'weight', 'factor_to_base' => '1.000000000', 'is_base' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'kg', 'name' => 'Kilogram', 'symbol' => 'kg', 'family' => 'weight', 'factor_to_base' => '1000.000000000', 'is_base' => false, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ml', 'name' => 'Milliliter', 'symbol' => 'ml', 'family' => 'volume', 'factor_to_base' => '1.000000000', 'is_base' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'L', 'name' => 'Liter', 'symbol' => 'L', 'family' => 'volume', 'factor_to_base' => '1000.000000000', 'is_base' => false, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'unit', 'name' => 'Unit', 'symbol' => 'unit', 'family' => 'count', 'factor_to_base' => '1.000000000', 'is_base' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'half_dozen', 'name' => 'Half dozen', 'symbol' => 'half dozen', 'family' => 'count', 'factor_to_base' => '6.000000000', 'is_base' => false, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'dozen', 'name' => 'Dozen', 'symbol' => 'dozen', 'family' => 'count', 'factor_to_base' => '12.000000000', 'is_base' => false, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_purchase_presentations');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_categories');

        foreach ([
            'inventory_require_stock_movements', 'inventory_validate_cost_layer', 'inventory_validate_stock_movement',
            'inventory_validate_presentation', 'inventory_validate_item_units', 'inventory_prevent_system_unit_mutation',
            'inventory_prevent_history_mutation',
        ] as $function) {
            DB::unprepared("DROP FUNCTION IF EXISTS {$function}();");
        }
    }
};
