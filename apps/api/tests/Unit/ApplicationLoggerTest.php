<?php

namespace Tests\Unit;

use App\Services\ApplicationLogger;
use PHPUnit\Framework\TestCase;

class ApplicationLoggerTest extends TestCase
{
    public function test_it_redacts_credentials_from_nested_context_and_messages(): void
    {
        $value = ApplicationLogger::sanitize([
            'password' => 'plain-password',
            'nested' => [
                'authorization' => 'Bearer internal-token',
                'connection' => 'postgresql://auditor:db-password@database:5432/auditor',
                'message' => 'token=service-token',
            ],
        ]);

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('plain-password', $encoded);
        $this->assertStringNotContainsString('internal-token', $encoded);
        $this->assertStringNotContainsString('db-password', $encoded);
        $this->assertStringNotContainsString('service-token', $encoded);
        $this->assertStringContainsString('[REDACTED]', $encoded);
    }
}
