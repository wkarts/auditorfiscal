<?php

namespace Tests\Feature;

use App\Services\ApplicationLogger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicationLoggerTimestampTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('application_logs');
        Schema::create('application_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('level');
            $table->string('component');
            $table->string('event');
            $table->text('message');
            $table->json('context');
            $table->uuid('analysis_batch_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('request_id')->nullable();
            $table->uuid('incident_id')->nullable();
            $table->unsignedSmallInteger('attempt')->nullable();
            $table->dateTime('created_at');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('application_logs');

        parent::tearDown();
    }

    public function test_it_persists_the_log_timestamp_in_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03T02:25:00-03:00'));

        ApplicationLogger::record('info', 'test', 'utc_timestamp', 'Timestamp test.');

        $stored = DB::table('application_logs')->value('created_at');
        $this->assertSame('2026-08-03 05:25:00', (string) $stored);
    }
}
