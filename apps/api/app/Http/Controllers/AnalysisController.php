<?php
namespace App\Http\Controllers; use App\Jobs\ProcessAnalysisBatch; use App\Models\{AnalysisBatch,FiscalCatalogVersion,FiscalDocument,Finding,SourceFile}; use App\Services\CompanyAccess; use Illuminate\Http\Request; use Illuminate\Support\Facades\Storage; use Illuminate\Support\Str;
class AnalysisController extends Controller {
 public function index(Request $r){$q=AnalysisBatch::with('company','catalogVersion')->withCount(['documents','findings','reports'])->whereIn('company_id',CompanyAccess::ids($r->user()));if($r->filled('company_id'))$q->where('company_id',$r->company_id);if($r->filled('status'))$q->where('status',$r->status);return $q->latest()->paginate(30);}
 public function store(Request $r)
 {
  $max=(int)env('MAX_UPLOAD_MB',500)*1024;
  $d=$r->validate(['company_id'=>'required|uuid|exists:companies,id','name'=>'required|string|max:255','period_start'=>'nullable|date','period_end'=>'nullable|date|after_or_equal:period_start','catalog_version_id'=>'nullable|uuid|exists:fiscal_catalog_versions,id','files'=>'required|array|min:1','files.*'=>"file|max:$max|mimes:xml,zip"]);
  CompanyAccess::ensure($r->user(),$d['company_id']);
  $catalog=$d['catalog_version_id']?FiscalCatalogVersion::whereKey($d['catalog_version_id'])->where('status','published')->firstOrFail():FiscalCatalogVersion::published()->latest('published_at')->firstOrFail();
  $disk=Storage::disk(config('filesystems.default'));$uploadedPaths=[];$batch=null;
  try {
   $batch=AnalysisBatch::create(['company_id'=>$d['company_id'],'catalog_version_id'=>$catalog->id,'name'=>$d['name'],'period_start'=>$d['period_start']??null,'period_end'=>$d['period_end']??null,'status'=>'uploading','total_files'=>count($d['files']),'created_by'=>$r->user()->id]);
   foreach($d['files'] as $file){$hash=hash_file('sha256',$file->getRealPath());$path="batches/{$batch->id}/sources/".Str::uuid().'-'.preg_replace('/[^A-Za-z0-9._-]/','_',$file->getClientOriginalName());$disk->put($path,file_get_contents($file->getRealPath()));$uploadedPaths[]=$path;SourceFile::create(['analysis_batch_id'=>$batch->id,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'sha256'=>$hash,'storage_path'=>$path]);}
   $batch->update(['status'=>'queued']);ProcessAnalysisBatch::dispatch($batch->id)->onQueue('high');
  } catch (\Throwable $exception) {
   if($uploadedPaths!==[]){try{$disk->delete($uploadedPaths);}catch(\Throwable $cleanupException){report($cleanupException);}}
   try{$batch?->delete();}catch(\Throwable $cleanupException){report($cleanupException);}
   report($exception);abort(503,'Não foi possível iniciar a auditoria. Verifique o armazenamento de objetos e a fila de processamento e tente novamente.');
  }
  return response()->json($batch->load('sourceFiles','catalogVersion'),202);
 }
 public function show(Request $r,AnalysisBatch $batch){CompanyAccess::ensure($r->user(),$batch->company_id);return $batch->load('company','catalogVersion','sourceFiles','reports')->loadCount(['documents','findings']);}
 public function documents(Request $r,AnalysisBatch $batch){CompanyAccess::ensure($r->user(),$batch->company_id);$q=$batch->documents()->withCount('items');foreach(['direction','status','number','issuer_tax_id','recipient_tax_id'] as $f)if($r->filled($f))$q->where($f,$r->$f);return $q->orderByDesc('issued_at')->paginate(min((int)$r->input('per_page',100),500));}
 public function document(Request $r,AnalysisBatch $batch,FiscalDocument $document){CompanyAccess::ensure($r->user(),$batch->company_id);abort_unless($document->analysis_batch_id===$batch->id,404);return $document->load('items','findings');}
 public function findings(Request $r,AnalysisBatch $batch){CompanyAccess::ensure($r->user(),$batch->company_id);$q=$batch->findings()->with(['fiscalDocument:id,number,access_key','fiscalItem:id,item_number,ncm,description']);foreach(['severity','category','status','rule_code'] as $f)if($r->filled($f))$q->where($f,$r->$f);return $q->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")->paginate(100);}
 public function resolve(Request $r,AnalysisBatch $batch,Finding $finding){CompanyAccess::ensure($r->user(),$batch->company_id);abort_unless($finding->analysis_batch_id===$batch->id,404);$d=$r->validate(['status'=>'required|in:open,in_review,resolved,dismissed','resolution_notes'=>'nullable|string','assigned_to'=>'nullable|exists:users,id']);$finding->update($d+['resolved_at'=>in_array($d['status'],['resolved','dismissed'])?now():null]);return $finding;}
 public function reprocess(Request $r,AnalysisBatch $batch){CompanyAccess::ensure($r->user(),$batch->company_id);$d=$r->validate(['catalog_version_id'=>'nullable|uuid|exists:fiscal_catalog_versions,id']);$new=$batch->replicate(['status','progress','processed_files','document_count','item_count','finding_count','summary','error','started_at','finished_at']);$new->id=(string)Str::uuid();$new->status='queued';$new->name=$batch->name.' — reprocessamento';$new->catalog_version_id=$d['catalog_version_id']??$batch->catalog_version_id;$new->created_by=$r->user()->id;$new->save();foreach($batch->sourceFiles as $f){$copy=$f->replicate();$copy->id=(string)Str::uuid();$copy->analysis_batch_id=$new->id;$copy->save();}ProcessAnalysisBatch::dispatch($new->id)->onQueue('high');return response()->json($new,202);}
}
