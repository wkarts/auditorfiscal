<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id', (string) Str::uuid());
        $request->attributes->set('request_id', $requestId);
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $request->user()) {
            $entity = $request->route('id') ?? $request->route('batch') ?? $request->route('version');
            AuditLog::create([
                'user_id' => $request->user()->id,
                'company_id' => $request->input('company_id'),
                'action' => $request->method().' '.$request->route()?->uri(),
                'entity_type' => $request->route()?->getName(),
                'entity_id' => $entity instanceof Model ? (string) $entity->getKey() : ($entity !== null ? (string) $entity : null),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'request_id' => $requestId,
                'metadata' => ['status' => $response->getStatusCode()],
            ]);
        }

        return $response;
    }
}
