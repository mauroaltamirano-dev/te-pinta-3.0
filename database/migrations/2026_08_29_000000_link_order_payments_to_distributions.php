<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('management_distribution_rules')->where('active', true)->count() > 1) {
            throw new RuntimeException(
                'Cannot select an automatic distribution rule while more than one distribution rule is active.'
            );
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX management_distribution_rules_single_active_unique
            ON management_distribution_rules ((1))
            WHERE active
            SQL);

        Schema::table('management_distributions', function (Blueprint $table): void {
            $table->foreignId('management_order_payment_id')
                ->nullable()
                ->after('source_wallet_id')
                ->unique('management_distributions_order_payment_unique')
                ->constrained('management_order_payments')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION management_validate_order_payment_distribution()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                source_payment management_order_payments%ROWTYPE;
            BEGIN
                IF NEW.management_order_payment_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT * INTO source_payment
                FROM management_order_payments
                WHERE id = NEW.management_order_payment_id;

                IF source_payment.id IS NULL
                    OR NEW.source_wallet_id <> source_payment.destination_wallet_id
                    OR NEW.amount <> source_payment.amount
                    OR NEW.effective_date <> source_payment.effective_date THEN
                    RAISE EXCEPTION 'Management distribution % must match its order payment source wallet, amount and effective date', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_distributions_validate_order_payment
            AFTER INSERT ON management_distributions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_order_payment_distribution();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('management_distributions')->whereNotNull('management_order_payment_id')->exists()) {
            throw new RuntimeException(
                'Cannot remove order payment provenance from existing automatic distributions.'
            );
        }

        DB::statement('DROP TRIGGER IF EXISTS management_distributions_validate_order_payment ON management_distributions');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_order_payment_distribution();');

        Schema::table('management_distributions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('management_order_payment_id');
        });

        DB::statement('DROP INDEX IF EXISTS management_distribution_rules_single_active_unique');
    }
};
