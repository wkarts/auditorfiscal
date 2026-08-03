<?php

namespace App\Services;

use App\Models\AnalysisBatch;
use App\Models\ApplicationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApplicationLogger
{
    private const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'cookie', 'app_key',
        'access_key', 'private_key', 'client_secret', 'credential',
    ];

    public static function record(
        string $level,
        string $component,
        string $event,
        string $message,
        array $context = [],
        ?AnalysisBatch $batch = null,
        ?int $userId = null,
        ?string $requestId = null,
        ?string $incidentId = null,
        ?int $attempt = null,
    ): ?ApplicationLog {
        $level = in_array($level, self::LEVELS, true) ? $level : 'info';
        $safeMessage = self::sanitizeMessage($message);
        $safeContext = self::sanitize($context);
        $baseContext = [
            'component' => $component,
            'event' => $event,
            'analysis_batch_id' => $batch?->id,
            'company_id' => $batch?->company_id,
            'request_id' => $requestId,
            'incident_id' => $incidentId,
            'attempt' => $attempt,
        ];

        Log::log($level, $safeMessage, array_filter($baseContext, fn ($value) => $value !== null) + $safeContext);

        try {
            return ApplicationLog::create([
                'level' => $level,
                'component' => mb_substr($component, 0, 80),
                'event' => mb_substr($event, 0, 120),
                'message' => $safeMessage,
                'context' => $safeContext,
                'analysis_batch_id' => $batch?->id,
                'company_id' => $batch?->company_id,
                'user_id' => $userId,
                'request_id' => $requestId,
                'incident_id' => $incidentId,
                'attempt' => $attempt,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Não foi possível persistir o log estruturado da aplicação.', [
                'component' => 'observability',
                'event' => 'log_persistence_failed',
                'exception_class' => $exception::class,
            ]);

            return null;
        }
    }

    public static function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $itemKey => $itemValue) {
                $sanitized[$itemKey] = self::sanitize($itemValue, (string) $itemKey);
            }

            return $sanitized;
        }

        if ($value instanceof Throwable) {
            return ['exception_class' => $value::class, 'message' => self::sanitizeMessage($value->getMessage())];
        }

        if (is_string($value)) {
            return self::sanitizeMessage($value);
        }

        if (is_object($value)) {
            return ['object_class' => $value::class];
        }

        return $value;
    }

    public static function sanitizeMessage(string $message): string
    {
        $message = preg_replace('#(://[^:/\s]+:)[^@\s]+@#', '$1[REDACTED]@', $message) ?? $message;
        $message = preg_replace('/\bBearer\s+[^\s,;]+/i', 'Bearer [REDACTED]', $message) ?? $message;
        $message = preg_replace(
            '/\b(password|passwd|secret|token|authorization|app[_-]?key|access[_-]?key)\b(\s*[=:]\s*)[^\s,;]+/i',
            '$1$2[REDACTED]',
            $message,
        ) ?? $message;

        return mb_substr($message, 0, 4000);
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = mb_strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
