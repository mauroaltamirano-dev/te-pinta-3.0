<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_story_schedules', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort_order')->default(1)->after('days_of_week');
            $table->index(['time', 'sort_order', 'id'], 'instagram_story_schedules_time_order_index');
        });

        Schema::table('instagram_story_publications', function (Blueprint $table): void {
            $table->unsignedSmallInteger('queue_order')->default(0)->after('scheduled_for');
            $table->boolean('retryable')->default(false)->after('failure_code');
            $table->timestampTz('cancelled_at')->nullable()->after('failed_at');
            $table->timestampTz('archived_at')->nullable()->after('cancelled_at');
            $table->index(['archived_at', 'scheduled_for', 'id'], 'instagram_story_publications_history_index');
            $table->index(['status', 'scheduled_for', 'queue_order', 'id'], 'instagram_story_publications_claim_order_index');
        });

        DB::statement('ALTER TABLE instagram_story_schedules ADD CONSTRAINT instagram_story_schedules_sort_order_check CHECK (sort_order BETWEEN 1 AND 999)');
        DB::statement('ALTER TABLE instagram_story_publications ADD CONSTRAINT instagram_story_publications_queue_order_check CHECK (queue_order >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE instagram_story_publications DROP CONSTRAINT IF EXISTS instagram_story_publications_queue_order_check');
        DB::statement('ALTER TABLE instagram_story_schedules DROP CONSTRAINT IF EXISTS instagram_story_schedules_sort_order_check');

        Schema::table('instagram_story_publications', function (Blueprint $table): void {
            $table->dropIndex('instagram_story_publications_claim_order_index');
            $table->dropIndex('instagram_story_publications_history_index');
            $table->dropColumn(['queue_order', 'retryable', 'cancelled_at', 'archived_at']);
        });

        Schema::table('instagram_story_schedules', function (Blueprint $table): void {
            $table->dropIndex('instagram_story_schedules_time_order_index');
            $table->dropColumn('sort_order');
        });
    }
};
