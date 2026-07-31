<?php
namespace App\Http\Controllers; use App\Models\ReportArtifact; use App\Services\CompanyAccess; use Illuminate\Http\Request; use Illuminate\Support\Facades\Storage;
class ReportController extends Controller {public function download(Request $r,ReportArtifact $report){$report->load('batch');CompanyAccess::ensure($r->user(),$report->batch->company_id);$ext=$report->type==='pdf'?'pdf':'xlsx';return Storage::disk(config('filesystems.default'))->download($report->storage_path,"auditoria-{$report->analysis_batch_id}.{$ext}");}}
