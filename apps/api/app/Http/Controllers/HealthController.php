<?php
namespace App\Http\Controllers; use Illuminate\Support\Facades\{DB,Redis,Storage};
class HealthController extends Controller {public function live(){return ['status'=>'ok','service'=>'api','time'=>now()->toIso8601String()];}public function ready(){try{DB::select('select 1');Redis::ping();return ['status'=>'ready','database'=>'ok','redis'=>'ok'];}catch(\Throwable $e){return response()->json(['status'=>'not_ready','error'=>$e->getMessage()],503);}}}
