<?php

namespace Tests\Unit;

use App\Services\AnalysisFailure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
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
}
