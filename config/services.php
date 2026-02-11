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
        'token' => env('POSTMARK_TOKEN'),
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

    /*
    |--------------------------------------------------------------------------
    | API RENIEC - Consulta de DNI
    |--------------------------------------------------------------------------
    |
    | Configuración para la API de RENIEC para consultar datos de personas
    | por DNI. Puedes usar diferentes proveedores:
    | - apis.net.pe (Recomendado)
    | - apisperu.com
    | - API oficial de RENIEC (si tienes acceso)
    |
    */

    'reniec' => [
        'token' => env('RENIEC_API_TOKEN'),
        'url' => env('RENIEC_API_URL', 'https://api.apis.net.pe/v1/dni'),
        
        // Alternativas:
        // 'url' => 'https://apiperu.dev/api/dni',
        // 'url' => 'https://dniruc.apisperu.com/api/v1/dni',
    ],

    /*
    |--------------------------------------------------------------------------
    | API MINEDU - Consulta de Instituciones Educativas
    |--------------------------------------------------------------------------
    |
    | Configuración para consultar datos de instituciones educativas
    | por código modular desde MINEDU o ESCALE
    |
    */

    'minedu' => [
        'token' => env('MINEDU_API_TOKEN'),
        'url' => env('MINEDU_API_URL', 'http://escale.minedu.gob.pe/api'),
        
        // Alternativa con datos públicos de MINEDU
        // 'url' => 'http://datos.minedu.gob.pe/api',
    ],

];