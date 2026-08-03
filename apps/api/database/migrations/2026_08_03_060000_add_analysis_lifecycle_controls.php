<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_batches', function (Blueprint $table): void {
            $table->timestampTz('cancel_requested_at')->nullable()->index();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deletion_reason')->nullable();
            $table->index(['company_id', 'deleted_at', 'created_at'], 'analysis_batches_company_deleted_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_batches', function (Blueprint $table): void {
            $table->dropIndex('analysis_batches_company_deleted_created_idx');
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn([
                'cancel_requested_at',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
                'deleted_at',
                'deleted_by',
                'deletion_reason',
            ]);
        });
    }
};
