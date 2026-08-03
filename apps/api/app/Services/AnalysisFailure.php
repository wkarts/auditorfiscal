<?php

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AnalysisFailure
{
    public static function httpStatus(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException => $exception->status,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }

    public static function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof QueryException) {
            $sqlState = self::databaseErrorInfo($exception)[0] ?? null;

            return ! is_string($sqlState) || (! str_starts_with($sqlState, '22') && ! str_starts_with($sqlState, '23'));
        }
        if ($exception instanceof RequestException) {
            return $exception->response->serverError() || in_array($exception->response->status(), [408, 429], true);
        }
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode() >= 500 || in_array($exception->getStatusCode(), [408, 429], true);
        }

        return ! ($exception instanceof ValidationException
            || $exception instanceof AuthenticationException
            || $exception instanceof AuthorizationException
            || $exception instanceof ModelNotFoundException);
    }

    public static function from(Throwable $exception, ?int $attempt = null): array
    {
        $incidentId = (string) Str::uuid();
        $code = 'PROCESSING_ERROR';
        $message = 'O processamento da auditoria falhou inesperadamente.';
        $httpStatus = null;
        $responseBody = null;
        $engineIncidentId = null;
        $engineTechnicalMessage = null;
        $databaseTechnicalMessage = null;

        if ($exception instanceof ConnectionException) {
            $code = 'FISCAL_ENGINE_UNAVAILABLE';
            $message = 'Não foi possível conectar ao motor fiscal.';
        } elseif ($exception instanceof RequestException) {
            $httpStatus = $exception->response->status();
            $responseBody = ApplicationLogger::sanitizeMessage($exception->response->body());
            $structuredResponse = $exception->response->json();
            if (is_array($structuredResponse)) {
                $engineIncidentId = isset($structuredResponse['incident_id'])
                    ? ApplicationLogger::sanitizeMessage((string) $structuredResponse['incident_id'])
                    : null;
                $engineTechnicalMessage = isset($structuredResponse['technical_message'])
                    ? ApplicationLogger::sanitizeMessage((string) $structuredResponse['technical_message'])
                    : null;
            }
            $code = 'FISCAL_ENGINE_HTTP_'.$httpStatus;
            $message = $httpStatus >= 500
                ? 'O motor fiscal retornou um erro interno.'
                : 'O motor fiscal recusou os dados enviados para processamento.';
        } elseif ($exception instanceof QueryException) {
            $code = 'DATABASE_ERROR';
            $message = 'O processamento falhou ao acessar o banco de dados.';
            $errorInfo = self::databaseErrorInfo($exception);
            $sqlState = $errorInfo[0] ?? null;
            $driverCode = $errorInfo[1] ?? null;
            $databaseTechnicalMessage = implode(' · ', array_filter([
                'Falha de banco de dados sem exposição do comando SQL ou dos parâmetros.',
                $sqlState ? 'SQLSTATE '.ApplicationLogger::sanitizeMessage((string) $sqlState) : null,
                $driverCode ? 'driver '.ApplicationLogger::sanitizeMessage((string) $driverCode) : null,
            ]));
        } elseif ($exception instanceof HttpExceptionInterface) {
            $httpStatus = $exception->getStatusCode();
            $code = 'HTTP_'.$httpStatus;
            $message = $exception->getMessage() ?: 'A operação não pôde ser concluída.';
        } elseif (str_contains(mb_strtolower($exception::class), 'filesystem')
            || str_contains(mb_strtolower($exception::class), 's3')
            || str_contains(mb_strtolower($exception::class), 'aws')) {
            $code = 'OBJECT_STORAGE_ERROR';
            $message = 'O processamento falhou ao acessar o armazenamento de arquivos.';
        }

        return array_filter([
            'code' => $code,
            'message' => ApplicationLogger::sanitizeMessage($message),
            'technical_message' => $engineTechnicalMessage ?: $databaseTechnicalMessage ?: ApplicationLogger::sanitizeMessage($exception->getMessage()),
            'exception' => $exception::class,
            'http_status' => $httpStatus,
            'response_body' => $responseBody ?: null,
            'incident_id' => $incidentId,
            'engine_incident_id' => $engineIncidentId,
            'attempt' => $attempt,
            'occurred_at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function databaseErrorInfo(QueryException $exception): array
    {
        $previous = $exception->getPrevious();

        return is_array($exception->errorInfo ?? null)
            ? $exception->errorInfo
            : (is_array($previous?->errorInfo ?? null) ? $previous->errorInfo : []);
    }
}
