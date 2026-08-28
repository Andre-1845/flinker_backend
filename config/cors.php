<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Antes deste arquivo existir, a aplicação usava o default do framework
| (`allowed_origins => ['*']`) — qualquer site podia chamar a API a partir do
| navegador de um usuário logado. Agora a lista de origens vem de
| CORS_ALLOWED_ORIGINS no .env (separadas por vírgula) — ajuste pro(s) domínio(s)
| reais do frontend antes de abrir a API pra um ambiente público.
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:8080'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
