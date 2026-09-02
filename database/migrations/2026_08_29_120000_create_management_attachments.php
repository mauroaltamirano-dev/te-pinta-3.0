<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions')->restrictOnDelete();
            $table->string('disk', 64);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('deletion_reason', 240)->nullable();

            $table->unique(['disk', 'path'], 'management_attachments_storage_unique');
            $table->index(
                ['financial_transaction_id', 'deleted_at', 'id'],
                'management_attachments_transaction_active_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE management_attachments
            ADD CONSTRAINT management_attachments_private_storage_valid CHECK (
                disk = 'local'
                AND path ~ '^management/attachments/[0-9]{4}/[0-9]{2}/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'
                AND path !~ '\.\.'
            ),
            ADD CONSTRAINT management_attachments_file_metadata_valid CHECK (
                mime_type IN ('application/pdf', 'image/jpeg', 'image/png', 'image/webp')
                AND size > 0
                AND size <= 10485760
                AND checksum_sha256 ~ '^[0-9a-f]{64}$'
                AND length(original_name) BETWEEN 1 AND 255
                AND original_name !~ E'[\\\\/]'
                AND original_name !~ '[[:cntrl:]]'
            ),
            ADD CONSTRAINT management_attachments_tombstone_valid CHECK (
                (deleted_at IS NULL AND deleted_by IS NULL AND deletion_reason IS NULL)
                OR
                (deleted_at IS NOT NULL AND deleted_by IS NOT NULL AND length(btrim(deletion_reason)) BETWEEN 3 AND 240)
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION management_protect_attachment_evidence()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Management attachment evidence cannot be physically deleted'
                        USING ERRCODE = '23514';
                END IF;

                IF OLD.deleted_at IS NULL
                    AND NEW.deleted_at IS NOT NULL
                    AND ROW(
                        NEW.financial_transaction_id, NEW.disk, NEW.path, NEW.original_name,
                        NEW.mime_type, NEW.size, NEW.checksum_sha256, NEW.uploaded_by, NEW.created_at
                    ) IS NOT DISTINCT FROM ROW(
                        OLD.financial_transaction_id, OLD.disk, OLD.path, OLD.original_name,
                        OLD.mime_type, OLD.size, OLD.checksum_sha256, OLD.uploaded_by, OLD.created_at
                    ) THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'Management attachment evidence is immutable except for one audited tombstone transition'
                    USING ERRCODE = '23514';
            END;
            $$;

            CREATE TRIGGER management_attachments_evidence_immutable
            BEFORE UPDATE OR DELETE ON management_attachments
            FOR EACH ROW EXECUTE FUNCTION management_protect_attachment_evidence();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('management_attachments');
        DB::unprepared('DROP FUNCTION IF EXISTS management_protect_attachment_evidence();');
    }
};
