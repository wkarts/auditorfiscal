<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn()=>response()->json(['name'=>'Auditor Fiscal API','version'=>trim(file_get_contents(base_path('../../VERSION')))]));
