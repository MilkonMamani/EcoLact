<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | Google Maps (Zonas/Rutas/Recorridos)
    |--------------------------------------------------------------------------
    |
    | GOOGLE_MAPS_API_KEY se imprime en el HTML (meta tag) para que la
    | Maps JavaScript API cargue en el navegador: es una clave pública por
    | diseño de esa API, nunca secreta. Restringe su uso en Google Cloud por
    | dominio/referrer HTTP y por API habilitada (ver
    | docs/rutas-recorridos-acopio.md, sección Google Maps). No la
    | confundas con una credencial de servidor: esta app no usa ninguna API
    | de Google exclusiva de servidor, así que no hay una clave "de backend"
    | separada que proteger aquí.
    */
    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
        'map_id' => env('GOOGLE_MAPS_MAP_ID'),
        // Punto de partida del mapa cuando el proveedor aún no tiene
        // ubicación (por defecto la ciudad de Puno). Configurable por .env.
        'default_lat' => (float) env('GOOGLE_MAPS_DEFAULT_LAT', -15.8402),
        'default_lng' => (float) env('GOOGLE_MAPS_DEFAULT_LNG', -70.0219),
        'default_zoom' => (int) env('GOOGLE_MAPS_DEFAULT_ZOOM', 10),
    ],

];
