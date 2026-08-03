<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalysisObservabilityMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['application_logs', 'analysis_batches', 'companies', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('companies', fn (Blueprint $table) => $table->uuid('id')->primary());
        Schema::create('analysis_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies');
            $table->uuid('catalog_version_id');
            $table->decimal('progress', 7, 4)->default(0);
            $table->timestampsTz();
        });
    }

    protected function tearDown(): void
    {
        foreach (['application_logs', 'analysis_batches', 'companies', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_migration_adds_and_removes_observability_schema_idempotently(): void
    {
        $migration = require database_path('migrations/2026_08_03_020000_add_analysis_observability.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('application_logs'));
        $this->assertTrue(Schema::hasColumns('analysis_batches', [
            'reprocessed_from_id', 'attempt_count', 'last_attempt_at',
        ]));

        $migration->down();
        $migration->down();

        $this->assertFalse(Schema::hasTable('application_logs'));
        $this->assertFalse(Schema::hasColumn('analysis_batches', 'reprocessed_from_id'));
        $this->assertFalse(Schema::hasColumn('analysis_batches', 'attempt_count'));
    }
}
