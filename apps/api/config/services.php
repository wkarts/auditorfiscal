<?php
return [
    'fiscal_engine' => ['url' => env('FISCAL_ENGINE_URL', 'http://auditor-fiscal-engine:8000'), 'token' => env('FISCAL_ENGINE_TOKEN')],
    'cnpj_lookup' => ['url' => env('CNPJ_LOOKUP_URL', 'https://brasilapi.com.br/api/cnpj/v1'), 'timeout' => env('CNPJ_LOOKUP_TIMEOUT', 8)],
];
