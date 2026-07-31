<?php

use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    $versionFile = base_path('../../VERSION');
    $version = is_readable($versionFile)
        ? trim((string) file_get_contents($versionFile))
        : 'dev';

    return response()->json([
        'name' => 'Auditor Fiscal API',
        'version' => $version,
    ]);
});
