<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API Token
    |--------------------------------------------------------------------------
    |
    | Der Access Token für die WhatsApp Business Cloud API.
    | Wird von Meta bereitgestellt nach der App-Registrierung.
    |
    | Hinweis: Dieser Token kann global hier gesetzt werden ODER pro Account
    | in der Datenbank. Wenn mehrere Phone Numbers zum selben Business Account
    | gehören, können sie den gleichen Token teilen.
    |
    | WICHTIG: phone_number_id wird NICHT hier gesetzt, sondern muss pro Account
    | in der Datenbank gespeichert werden, da jede Phone Number ihre eigene
    | Meta Phone Number ID hat.
    |
    */
    'api_token' => env('WHATSAPP_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business ID
    |--------------------------------------------------------------------------
    |
    | Die Business Account ID von Meta (z.B. 3870XXXXXXXX).
    |
    | Hinweis: Diese ID kann global hier gesetzt werden ODER pro Account
    | in der Datenbank. Wenn mehrere Phone Numbers zum selben Business Account
    | gehören, können sie die gleiche business_id teilen.
    |
    | WICHTIG: phone_number_id (z.B. 366239283XXXXX) wird NICHT hier gesetzt,
    | sondern muss pro Account in der Datenbank gespeichert werden, da jede
    | Phone Number ihre eigene Phone Number ID hat.
    |
    */
    'business_id' => env('WHATSAPP_BUSINESS_ID'),

    /*
    |--------------------------------------------------------------------------
    | Meta App ID und Secret (für OAuth)
    |--------------------------------------------------------------------------
    |
    | HINWEIS: Diese Werte werden jetzt aus dem Meta-OAuth-Package geholt.
    | Bitte verwende META_APP_ID und META_APP_SECRET in deiner .env.
    |
    | Diese Werte hier sind deprecated und werden nur als Fallback verwendet.
    |
    */
    'app_id'     => env('WHATSAPP_APP_ID', env('META_APP_ID')),
    'app_secret' => env('WHATSAPP_APP_SECRET', env('META_APP_SECRET')),

    /*
    |--------------------------------------------------------------------------
    | API-Version (Meta Graph API)
    |--------------------------------------------------------------------------
    |
    | API-Version für die WhatsApp Business Cloud API.
    | Standard ist v21.0 (wie im bestehenden Code).
    |
    */
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),

    /*
    |--------------------------------------------------------------------------
    | API-Endpunkt (Meta Graph API)
    |--------------------------------------------------------------------------
    |
    | Basis-URL für die WhatsApp Business Cloud API.
    | Wird automatisch mit api_version zusammengesetzt, kann aber auch
    | komplett überschrieben werden.
    |
    */
    'api_url' => env('WHATSAPP_API_URL', null), // null = wird automatisch aus api_version gebaut

    /*
    |--------------------------------------------------------------------------
    | Webhook-Verifizierung
    |--------------------------------------------------------------------------
    |
    | Token für die Webhook-Verifizierung und Signatur-Validierung.
    |
    */
    'webhook' => [
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'secret'       => env('WHATSAPP_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queueing für ausgehende Nachrichten
    |--------------------------------------------------------------------------
    |
    | Optionen für asynchrones Versenden von Nachrichten.
    |
    */
    'queue' => [
        'enabled'    => env('WHATSAPP_QUEUE', false),
        'connection' => env('WHATSAPP_QUEUE_CONNECTION'),
        'queue'      => env('WHATSAPP_QUEUE_NAME', 'whatsapp'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Standard-Einstellungen für Nachrichten
    |--------------------------------------------------------------------------
    |
    | Voreinstellungen für das Versenden von Nachrichten.
    |
    */
    'defaults' => [
        'preview_url' => env('WHATSAPP_PREVIEW_URL', true),
    ],

];

