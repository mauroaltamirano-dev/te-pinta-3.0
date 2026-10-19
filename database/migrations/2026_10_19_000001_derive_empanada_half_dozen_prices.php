<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The empanada half dozen is no longer entered: it is six units at the stored unit price
 * (docs/ORDERS_AND_PRICING.md). Historical orders keep their own snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Soft-deleted empanadas included, like every unit price recalculation.
        DB::table('products')
            ->where('pricing_family', 'empanada')
            ->update(['half_dozen_price' => DB::raw('6 * unit_price'), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The previously entered half-dozen prices are not recoverable and never priced orders; keep the derived values.
    }
};
