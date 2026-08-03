<?php

namespace Tests\Unit;

use App\Services\AnalysisFailure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use PDOException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AnalysisFailureHttpStatusTest extends TestCase
{
    public function test_it_preserves_known_client_error_statuses(): void
    {
        $validation = ValidationException::withMessages(['email' => 'Credenciais inválidas.']);

        $this->assertSame(422, AnalysisFailure::httpStatus($validation));
        $this->assertSame(401, AnalysisFailure::httpStatus(new AuthenticationException));
        $this->assertSame(403, AnalysisFailure::httpStatus(new AuthorizationException));
        $this->assertSame(404, AnalysisFailure::httpStatus(new ModelNotFoundException));
        $this->assertSame(429, AnalysisFailure::httpStatus(new HttpException(429)));
    }

    public function test_it_classifies_only_unmapped_exceptions_as_internal_errors(): void
    {
        $this->assertSame(500, AnalysisFailure::httpStatus(new RuntimeException('unexpected')));
    }

    public function test_it_does_not_expose_sql_or_bindings_in_database_failures(): void
    {
        $previous = new PDOException('duplicate key with private fiscal payload');
        $previous->errorInfo = ['23505', 7, 'private fiscal payload'];
        $exception = new QueryException(
            'pgsql',
            'insert into fiscal_documents (normalized) values (?)',
            ['private fiscal payload'],
            $previous,
        );

        $failure = AnalysisFailure::from($exception);

        $this->assertSame('DATABASE_ERROR', $failure['code']);
        $this->assertStringContainsString('SQLSTATE 23505', $failure['technical_message']);
        $this->assertStringNotContainsString('insert into', $failure['technical_message']);
        $this->assertStringNotContainsString('private fiscal payload', $failure['technical_message']);
        $this->assertFalse(AnalysisFailure::isRetryable($exception));
        $this->assertTrue(AnalysisFailure::isRetryable(new RuntimeException('temporary internal failure')));
    }
}
