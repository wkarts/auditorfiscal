<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('analysis_batches', 'reprocessed_from_id')) {
            Schema::table('analysis_batches', function (Blueprint $table): void {
                $table->foreignUuid('reprocessed_from_id')->nullable()->after('catalog_version_id')
                    ->constrained('analysis_batches')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('analysis_batches', 'attempt_count') || ! Schema::hasColumn('analysis_batches', 'last_attempt_at')) {
            Schema::table('analysis_batches', function (Blueprint $table): void {
                if (! Schema::hasColumn('analysis_batches', 'attempt_count')) {
                    $table->unsignedSmallInteger('attempt_count')->default(0)->after('progress');
                }
                if (! Schema::hasColumn('analysis_batches', 'last_attempt_at')) {
                    $table->timestampTz('last_attempt_at')->nullable()->after('attempt_count');
                }
            });
        }

        if (! Schema::hasTable('application_logs')) {
            Schema::create('application_logs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('level', 20)->index();
                $table->string('component', 80)->index();
                $table->string('event', 120)->index();
                $table->text('message');
                $table->jsonb('context')->default('{}');
                $table->foreignUuid('analysis_batch_id')->nullable()->constrained('analysis_batches')->nullOnDelete();
                $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('request_id')->nullable()->index();
                $table->uuid('incident_id')->nullable()->index();
                $table->unsignedSmallInteger('attempt')->nullable();
                $table->timestampTz('created_at')->useCurrent()->index();
                $table->index(['analysis_batch_id', 'created_at'], 'application_logs_batch_created_idx');
                $table->index(['component', 'level', 'created_at'], 'application_logs_component_level_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_logs');

        if (Schema::hasColumn('analysis_batches', 'reprocessed_from_id')) {
            Schema::table('analysis_batches', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('reprocessed_from_id');
            });
        }

        $attemptColumns = array_values(array_filter(
            ['attempt_count', 'last_attempt_at'],
            fn (string $column): bool => Schema::hasColumn('analysis_batches', $column),
        ));
        if ($attemptColumns !== []) {
            Schema::table('analysis_batches', function (Blueprint $table) use ($attemptColumns): void {
                $table->dropColumn($attemptColumns);
            });
        }
    }
};
