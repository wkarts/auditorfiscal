<?php
namespace App\Http\Controllers; use App\Models\ReportArtifact; use App\Services\AnalysisAccess; use Illuminate\Http\Request; use Illuminate\Support\Facades\Storage;
class ReportController extends Controller {public function download(Request $r,ReportArtifact $report){$report->load('batch');AnalysisAccess::ensure($r->user(),$report->batch);$ext=$report->type==='pdf'?'pdf':'xlsx';return Storage::disk(config('filesystems.default'))->download($report->storage_path,"auditoria-{$report->analysis_batch_id}.{$ext}");}}
