<?php
namespace Database\Seeders;
use App\Models\FiscalCatalogVersion; use Illuminate\Database\Seeder; use Illuminate\Support\Facades\DB; use Illuminate\Support\Str; use RuntimeException;
class FiscalCatalogSeeder extends Seeder {
 private string $dir;
 public function run():void{
  $this->dir=database_path('seeders/data');$manifest=json_decode(file_get_contents($this->dir.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
  DB::transaction(function()use($manifest){
   $version=FiscalCatalogVersion::firstOrCreate(['version'=>$manifest['version']],[
    'name'=>'Classificação Tributária Final — NCM × cClassTrib','status'=>'published','valid_from'=>'2026-01-01',
    'source_filename'=>$manifest['sources'][0]['name'],'source_sha256'=>$manifest['sources'][0]['sha256'],'manifest'=>$manifest,
    'approved_at'=>now(),'published_at'=>now(),
   ]);
   if($version->cstEntries()->exists()&&$version->ncmEntries()->exists())return;
   $now=now();
   $this->loadGzip('cst_catalog.jsonl.gz',function(array $r)use($version,$now){return ['catalog_version_id'=>$version->id,'cst'=>$r['cst'],'description'=>$r['description'],'applicable_nfe'=>$r['applicable_nfe'],'indicators'=>json_encode($r['indicators'],JSON_UNESCAPED_UNICODE),'source_sheet'=>$r['source_sheet'],'source_row'=>$r['source_row'],'created_at'=>$now,'updated_at'=>$now];},'cst_catalog_entries');
   $this->loadGzip('cclass_catalog.jsonl.gz',function(array $r)use($version,$now){return ['catalog_version_id'=>$version->id,'cclass_trib'=>$r['cclass_trib'],'cst'=>$r['cst'],'name'=>$r['name'],'description'=>$r['description'],'legal_basis'=>$r['legal_basis'],'law_text'=>$r['law_text'],'rate_type'=>$r['rate_type'],'ibs_reduction_percent'=>$r['ibs_reduction_percent'],'cbs_reduction_percent'=>$r['cbs_reduction_percent'],'valid_from'=>$r['valid_from'],'valid_to'=>$r['valid_to'],'updated_at_source'=>$r['updated_at_source'],'applicable_nfe'=>$r['applicable_nfe'],'source_url'=>$r['source_url'],'indicators'=>json_encode($r['indicators'],JSON_UNESCAPED_UNICODE),'format_warning'=>$r['format_warning'],'source_sheet'=>$r['source_sheet'],'source_row'=>$r['source_row'],'created_at'=>$now,'updated_at'=>$now];},'cclass_catalog_entries');
   $issueRows=[];
   $this->loadGzip('ncm_class_trib.jsonl.gz',function(array $r)use($version,$now,&$issueRows){
    foreach($r['validation_issues'] as $issue)$issueRows[]=['catalog_version_id'=>$version->id,'code'=>$issue['code'],'severity'=>$issue['severity'],'source_sheet'=>$r['source_sheet'],'source_row'=>$r['source_row'],'message'=>$this->message($issue['code']),'context'=>json_encode($issue,JSON_UNESCAPED_UNICODE),'created_at'=>$now,'updated_at'=>$now];
    if(count($issueRows)>=500){DB::table('catalog_import_issues')->insert($issueRows);$issueRows=[];}
    return ['id'=>(string)Str::uuid(),'catalog_version_id'=>$version->id,'ncm_raw'=>$r['ncm_raw'],'ncm'=>$r['ncm'],'ncm_level'=>$r['ncm_level'],'ex_code'=>$r['ex_code'],'description'=>$r['description'],'reference_rate'=>$r['reference_rate'],'expected_cst'=>$r['expected_cst'],'expected_cclass_trib'=>$r['expected_cclass_trib'],'reduction_type'=>$r['reduction_type'],'legal_reference_raw'=>$r['legal_reference_raw'],'conditions'=>'{}','valid_from'=>'2026-01-01','valid_to'=>null,'allow_child_inheritance'=>false,'inherited_ncm'=>$r['inherited_ncm'],'status'=>$r['status'],'validation_issues'=>json_encode($r['validation_issues'],JSON_UNESCAPED_UNICODE),'source_sheet'=>$r['source_sheet'],'source_row'=>$r['source_row'],'created_at'=>$now,'updated_at'=>$now];
   },'ncm_class_trib_entries');
   if($issueRows)DB::table('catalog_import_issues')->insert($issueRows);
  },5);
 }
 private function loadGzip(string $file,callable $map,string $table):void{$h=gzopen($this->dir.'/'.$file,'rb');if(!$h)throw new RuntimeException("Falha ao abrir $file");$chunk=[];while(!gzeof($h)){$line=trim(gzgets($h));if($line==='')continue;$chunk[]=$map(json_decode($line,true,512,JSON_THROW_ON_ERROR));if(count($chunk)>=500){DB::table($table)->insert($chunk);$chunk=[];}}if($chunk)DB::table($table)->insert($chunk);gzclose($h);}
 private function message(string $code):string{return match($code){'MISSING_CLASSIFICATION'=>'NCM sem CST/cClassTrib parametrizado.','INVALID_CST'=>'CST inválido ou fora do catálogo oficial.','MISSING_CCLASS'=>'CST informado sem cClassTrib.','CST_CCLASS_MISMATCH'=>'CST incompatível com a cClassTrib oficial.','REDUCTION_CONFLICT'=>'Percentual textual de redução diverge do catálogo oficial.',default=>'Inconsistência de parametrização.'};}
}
