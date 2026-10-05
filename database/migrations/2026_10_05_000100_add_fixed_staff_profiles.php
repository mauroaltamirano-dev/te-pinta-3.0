<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('staff_profile', 16)->nullable();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_staff_profile_valid CHECK (staff_profile IS NULL OR staff_profile IN ('waiter', 'cashier'))");
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('staff_profile')->exists()) {
            throw new RuntimeException('Staff profiles are in use; explicitly reconcile access before rollback.');
        }
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_staff_profile_valid');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('staff_profile');
        });
    }
};
