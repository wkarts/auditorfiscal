<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AnalysisFailure
{
    public static function from(Throwable $exception, ?int $attempt = null): array
    {
        $incidentId = (string) Str::uuid();
        $code = 'PROCESSING_ERROR';
        $message = 'O processamento da auditoria falhou inesperadamente.';
        $httpStatus = null;
        $responseBody = null;
        $engineIncidentId = null;
        $engineTechnicalMessage = null;

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
            'technical_message' => $engineTechnicalMessage ?: ApplicationLogger::sanitizeMessage($exception->getMessage()),
            'exception' => $exception::class,
            'http_status' => $httpStatus,
            'response_body' => $responseBody ?: null,
            'incident_id' => $incidentId,
            'engine_incident_id' => $engineIncidentId,
            'attempt' => $attempt,
            'occurred_at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
