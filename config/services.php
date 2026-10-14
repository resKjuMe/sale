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

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // Titel ist eine einfache Bildbeschreibung, Etiketten lesen braucht mehr.
        'models' => [
            'brand_size' => env('ANTHROPIC_MODEL_BRAND_SIZE', 'claude-sonnet-5-5'),
            'title' => env('ANTHROPIC_MODEL_TITLE', 'claude-haiku-5-5'),
            'price' => env('ANTHROPIC_MODEL_PRICE', 'claude-sonnet-5-5'),
        ],
        // Nur für Keys, die an keinen Workspace gebunden sind.
        'workspace' => env('ANTHROPIC_WORKSPACE_ID'),
        // $ je Million Tokens [Eingabe, Ausgabe]; Cache-Lesen 0,1×, Cache-Schreiben 1,25× Eingabe.
        'prices' => [
            'claude-haiku-5-5' => [0.10, 0.50],
            'claude-sonnet-5-5' => [2.00, 10.00],
            'claude-sonnet-5' => [2.00, 10.00],
            'claude-opus-5-5' => [4.00, 20.00],
            'claude-opus-5' => [5.00, 25.00],
            'claude-opus-4-8' => [5.00, 25.00],
            'claude-fable-5-1' => [10.00, 50.00],
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
