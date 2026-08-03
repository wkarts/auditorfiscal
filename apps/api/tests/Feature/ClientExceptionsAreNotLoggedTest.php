<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientExceptionsAreNotLoggedTest extends TestCase
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
        Schema::dropIfExists('application_logs');

        parent::tearDown();
    }

    public function test_validation_and_authentication_errors_keep_their_status_without_internal_incidents(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->getJson('/api/v1/dashboard')->assertUnauthorized();

        $this->assertDatabaseCount('application_logs', 0);
    }
}
