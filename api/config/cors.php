<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // Orígenes permitidos. Se toman de FRONTEND_URL (lista separada por comas)
    // para no dejar la API abierta a cualquier origen en producción. Por defecto,
    // en desarrollo local, se permiten los puertos habituales de Live Server y `npx serve`.
    'allowed_origins' => array_map('trim', explode(',', env(
        'FRONTEND_URL',
        'http://localhost:5500,http://127.0.0.1:5500,http://localhost:3000'
    ))),

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];