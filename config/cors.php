<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Ce fichier ne concerne que les appels faits depuis un navigateur (ex: un
    | futur dashboard web ou Flutter Web). Les apps Flutter mobiles (Android/iOS)
    | ne sont PAS soumises à la politique CORS, donc ce fichier n'affecte pas
    | les tests via USB (adb reverse) ou wifi local. On le laisse permissif sur
    | les routes API pour ne jamais bloquer un client legitime par erreur.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
