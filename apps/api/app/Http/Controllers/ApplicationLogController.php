<?php

namespace App\Http\Controllers;

use App\Models\ApplicationLog;
use Illuminate\Http\Request;

class ApplicationLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'level' => 'nullable|in:debug,info,notice,warning,error,critical,alert,emergency',
            'component' => 'nullable|string|max:80',
            'event' => 'nullable|string|max:120',
            'analysis_batch_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);
        $query = ApplicationLog::query()->with('analysisBatch:id,name,status');

        foreach (['level', 'component', 'event', 'analysis_batch_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (! empty($data['search'])) {
            $search = '%'.mb_strtolower($data['search']).'%';
            $query->where(function ($nested) use ($search): void {
                $nested->whereRaw('LOWER(message) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(event) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(component) LIKE ?', [$search]);
            });
        }

        return $query->latest('id')->paginate($data['per_page'] ?? 100);
    }
}
