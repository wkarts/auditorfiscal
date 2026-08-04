<?php

namespace Tests\Unit;

use App\Services\AnalysisFailure;
use App\Services\AnalysisResultValidationException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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

    public function test_it_preserves_non_retryable_engine_input_error_contract(): void
    {
        $response = new Response(new Psr7Response(422, ['Content-Type' => 'application/json'], json_encode([
            'detail' => 'O XML não pertence ao cliente auditado selecionado.',
            'error_code' => 'XML_COMPANY_MISMATCH',
            'technical_message' => 'O CNPJ selecionado não consta como emitente nem destinatário.',
        ])));
        $exception = new RequestException($response);
        $failure = AnalysisFailure::from($exception);

        $this->assertSame('XML_COMPANY_MISMATCH', $failure['code']);
        $this->assertSame('O XML não pertence ao cliente auditado selecionado.', $failure['message']);
        $this->assertFalse(AnalysisFailure::isRetryable($exception));
        $this->assertFalse(AnalysisFailure::isRetryable(new AnalysisResultValidationException('XML_DUPLICATE_ITEM_NUMBER', 'duplicado')));
    }
}
