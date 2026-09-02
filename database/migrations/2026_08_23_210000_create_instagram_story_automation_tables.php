<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_automation_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->timestampsTz();
        });
        DB::table('instagram_automation_settings')->insert([
            'id' => 1,
            'enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('instagram_story_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('category', 64)->default('other');
            $table->string('file_path', 500)->unique();
            $table->string('mime_type', 64);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->index(['is_active', 'name']);
        });

        Schema::create('instagram_story_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('instagram_story_assets')->restrictOnDelete();
            $table->string('name', 160);
            $table->time('time');
            $table->jsonb('days_of_week');
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();
            $table->index(['is_active', 'time']);
        });

        Schema::create('instagram_story_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained('instagram_story_schedules')->nullOnDelete();
            $table->foreignId('asset_id')->constrained('instagram_story_assets')->restrictOnDelete();
            $table->timestampTz('scheduled_for');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('processing_started_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->boolean('requires_manual_review')->default(false);
            $table->string('meta_container_id', 255)->nullable();
            $table->string('meta_media_id', 255)->nullable();
            $table->timestampsTz();
            $table->unique(['schedule_id', 'scheduled_for'], 'instagram_story_publications_schedule_due_unique');
            $table->index(['status', 'scheduled_for', 'id']);
            $table->index(['status', 'processing_started_at']);
        });

        DB::statement("ALTER TABLE instagram_story_publications ADD CONSTRAINT instagram_story_publications_status_check CHECK (status IN ('pending', 'processing', 'published', 'failed', 'cancelled'))");
        DB::statement('ALTER TABLE instagram_story_publications ADD CONSTRAINT instagram_story_publications_attempts_check CHECK (attempts >= 0)');
        DB::statement('ALTER TABLE instagram_story_assets ADD CONSTRAINT instagram_story_assets_dimensions_check CHECK (width > 0 AND height > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_story_publications');
        Schema::dropIfExists('instagram_story_schedules');
        Schema::dropIfExists('instagram_story_assets');
        Schema::dropIfExists('instagram_automation_settings');
    }
};
