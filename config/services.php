<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ==========================================================================
    // FOURNISSEURS DE PAIEMENT MOBILE MONEY
    // ==========================================================================
    // TODO GÉNÉRAL : tout ici est un placeholder tant qu'aucune clé API réelle
    // n'a été obtenue (opérateur direct OU agrégateur type DEXCHANGE — pas
    // encore décidé). Le jour où une intégration réelle démarre :
    //   1. Remplir api_key / base_url / webhook_secret dans .env.
    //   2. Ajuster signature_header / signature_algo / *_field / status_map
    //      selon la vraie documentation de l'opérateur.
    //   3. Si le format est vraiment différent (signature RSA, payload
    //      imbriqué...), créer une classe dédiée dans
    //      app/Services/PaymentProviders/ implémentant PaymentProviderInterface
    //      au lieu de réutiliser GenericMobileMoneyProvider — voir
    //      PaymentProviderFactory. Aucun changement nécessaire dans
    //      WebhookController dans les deux cas.
    //
    // "aggregator_dexchange" couvre le cas où un agrégateur unique gère
    // plusieurs opérateurs derrière une seule intégration — les deux modèles
    // (opérateurs directs OU agrégateur) sont supportés sans changement
    // d'architecture, seul le choix effectif reste à trancher.
    'payment_providers' => [
        'wave' => [
            'api_key' => env('WAVE_API_KEY'),
            'base_url' => env('WAVE_BASE_URL'),
            'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
            'signature_algo' => 'sha256',
            // Nom du champ du payload qui nous renvoie NOTRE référence
            // (l'UUID de la Transaction transmis au moment du transfert).
            'merchant_reference_field' => 'reference',
            // Nom du champ contenant l'ID de transaction PROPRE À L'OPÉRATEUR
            // (sert uniquement à l'idempotence/l'audit, pas à retrouver notre
            // Transaction).
            'external_reference_field' => 'external_reference',
            'status_field' => 'status',
            // Traduit les valeurs de statut de l'opérateur vers les nôtres
            // (pending est géré en interne, jamais attendu ici).
            'status_map' => ['completed' => 'completed', 'failed' => 'failed'],
        ],
        'orange_money' => [
            'api_key' => env('ORANGE_MONEY_API_KEY'),
            'base_url' => env('ORANGE_MONEY_BASE_URL'),
            'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
            'signature_algo' => 'sha256',
            'merchant_reference_field' => 'reference',
            'external_reference_field' => 'external_reference',
            'status_field' => 'status',
            'status_map' => ['completed' => 'completed', 'failed' => 'failed'],
        ],
        'mtn_momo' => [
            'api_key' => env('MTN_MOMO_API_KEY'),
            'base_url' => env('MTN_MOMO_BASE_URL'),
            'webhook_secret' => env('MTN_MOMO_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
            'signature_algo' => 'sha256',
            'merchant_reference_field' => 'reference',
            'external_reference_field' => 'external_reference',
            'status_field' => 'status',
            'status_map' => ['completed' => 'completed', 'failed' => 'failed'],
        ],
        'moov_money' => [
            'api_key' => env('MOOV_MONEY_API_KEY'),
            'base_url' => env('MOOV_MONEY_BASE_URL'),
            'webhook_secret' => env('MOOV_MONEY_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
            'signature_algo' => 'sha256',
            'merchant_reference_field' => 'reference',
            'external_reference_field' => 'external_reference',
            'status_field' => 'status',
            'status_map' => ['completed' => 'completed', 'failed' => 'failed'],
        ],
        'aggregator_dexchange' => [
            'api_key' => env('DEXCHANGE_API_KEY'),
            'base_url' => env('DEXCHANGE_BASE_URL'),
            'webhook_secret' => env('DEXCHANGE_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
            'signature_algo' => 'sha256',
            'merchant_reference_field' => 'reference',
            'external_reference_field' => 'external_reference',
            'status_field' => 'status',
            'status_map' => ['completed' => 'completed', 'failed' => 'failed'],
        ],
    ],

];
