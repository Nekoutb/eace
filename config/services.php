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

    // Invoice OCR providers. Resolution order (see AppServiceProvider): Anthropic
    // Claude when its key is set, else Azure Document Intelligence, else the fake
    // reader that backs the demo. Keys live in .env — never handled in code.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],

    'azure_di' => [
        'endpoint' => env('AZURE_DI_ENDPOINT'),
        'key' => env('AZURE_DI_KEY'),
    ],

    // Headless Chromium used to capture live evidence snapshots (Harmony2 ACF
    // authenticity, DGI consultation status). On Windows, Puppeteer's auto-
    // resolver fails when the user path contains a space, so we pin the binary
    // explicitly. Leave chrome_path empty to fall back to auto-detection.
    // The live connectors are only engaged when 'enabled' is true.
    'browsershot' => [
        'enabled' => (bool) env('BROWSERSHOT_ENABLED', false),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'node_path' => env('BROWSERSHOT_NODE_PATH'),
        'timeout' => (int) env('BROWSERSHOT_TIMEOUT', 90),
    ],

    // Live evidence portals. URLs are templated with the relevant reference.
    'harmony2' => [
        'acf_url' => env('HARMONY2_ACF_URL', 'https://fanr.harmony2.cm/authentification'),
    ],

    'dgi' => [
        // The official "Vérifier un NIU" portal (returns the taxpayer card + Etat).
        // HTTP only — the driver disables Chrome's HTTPS upgrade for it.
        'consultation_url' => env('DGI_CONSULTATION_URL', 'http://fiscalis.dgi.cm/modules/Common/Account/Login.aspx'),
    ],

];
