<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->text('auxiliary_document_storage_path')->nullable()->after('danfe_storage_path');
            $table->string('auxiliary_document_type', 24)->nullable()->after('auxiliary_document_storage_path');
            $table->string('auxiliary_document_source', 32)->nullable()->after('auxiliary_document_type');
            $table->string('auxiliary_document_status', 24)->default('pending')->after('auxiliary_document_source');
            $table->jsonb('auxiliary_document_error')->nullable()->after('auxiliary_document_status');
            $table->timestampTz('auxiliary_document_generated_at')->nullable()->after('auxiliary_document_error');
        });

        DB::table('fiscal_documents')
            ->whereNotNull('danfe_storage_path')
            ->update([
                'auxiliary_document_storage_path' => DB::raw('danfe_storage_path'),
                'auxiliary_document_type' => DB::raw("CASE WHEN model = '65' THEN 'DANFCE' ELSE 'DANFE' END"),
                'auxiliary_document_source' => 'imported_original',
                'auxiliary_document_status' => 'available',
            ]);
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->dropColumn([
                'auxiliary_document_storage_path',
                'auxiliary_document_type',
                'auxiliary_document_source',
                'auxiliary_document_status',
                'auxiliary_document_error',
                'auxiliary_document_generated_at',
            ]);
        });
    }
};
