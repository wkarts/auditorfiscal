<?php

namespace App\Http\Controllers;

use App\Models\ApplicationLog;
use App\Services\CompanyAccess;
use App\Services\TenantAccess;
use Illuminate\Http\Request;

class ApplicationLogController extends Controller
{
    public function index(Request $request)
    {
        [$data, $query] = $this->filtered($request);
        return $query->with('analysisBatch:id,name,status')->latest('id')->paginate($data['per_page'] ?? 100);
    }

    public function download(Request $request, ApplicationLog $log)
    {
        $this->ensureAccess($request, $log);
        return response()->json($log->load('analysisBatch:id,name,status'), 200, [
            'Content-Disposition' => 'attachment; filename="application-log-'.$log->id.'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function export(Request $request)
    {
        [, $query] = $this->filtered($request);
        return response()->streamDownload(function () use ($query): void {
            $query->oldest('id')->chunkById(500, function ($logs): void {
                foreach ($logs as $log) echo json_encode($log->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
            });
        }, 'application-logs-'.now()->format('Ymd-His').'.ndjson', ['Content-Type' => 'application/x-ndjson; charset=UTF-8']);
    }

    private function filtered(Request $request): array
    {
        $data = $request->validate([
            'level' => 'nullable|in:debug,info,notice,warning,error,critical,alert,emergency',
            'component' => 'nullable|string|max:80', 'event' => 'nullable|string|max:120',
            'analysis_batch_id' => 'nullable|uuid', 'search' => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);
        $query = ApplicationLog::query();
        if (! TenantAccess::isPlatformAdmin($request->user())) $query->whereIn('company_id', CompanyAccess::ids($request->user()));
        foreach (['level', 'component', 'event', 'analysis_batch_id'] as $field) if (! empty($data[$field])) $query->where($field, $data[$field]);
        if (! empty($data['search'])) {
            $search = '%'.mb_strtolower($data['search']).'%';
            $query->where(fn ($nested) => $nested->whereRaw('LOWER(message) LIKE ?', [$search])->orWhereRaw('LOWER(event) LIKE ?', [$search])->orWhereRaw('LOWER(component) LIKE ?', [$search]));
        }
        return [$data, $query];
    }

    private function ensureAccess(Request $request, ApplicationLog $log): void
    {
        if (TenantAccess::isPlatformAdmin($request->user())) return;
        abort_unless($log->company_id && CompanyAccess::ids($request->user())->contains($log->company_id), 404);
    }
}
