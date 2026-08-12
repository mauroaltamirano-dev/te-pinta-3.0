<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('phone_normalized', 40)->nullable()->after('phone');
            $table->text('notes')->nullable()->after('address');
            $table->index('phone_normalized', 'customers_phone_normalized_index');
        });

        DB::table('customers')->whereNotNull('phone')->orderBy('id')->each(function (object $customer): void {
            $normalized = preg_replace('/\D+/', '', trim((string) $customer->phone));
            DB::table('customers')->where('id', $customer->id)->update([
                'phone_normalized' => $normalized === '' ? null : $normalized,
            ]);
        });

        DB::statement('CREATE UNIQUE INDEX customers_active_phone_normalized_unique ON customers (phone_normalized) WHERE phone_normalized IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customers_active_phone_normalized_unique');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_phone_normalized_index');
            $table->dropColumn(['phone_normalized', 'notes']);
        });
    }
};
