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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fraud_checker' => [
        'outbound_ip' => env('FRAUD_CHECKER_OUTBOUND_IP', '162.0.209.109'),
    ],

    'meta_capi' => [
        // Test Events must be an explicit deployment-time choice. A saved code
        // alone can never put production traffic into Meta's test stream.
        'test_mode' => env('META_CAPI_TEST_MODE', false),
        'test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),
    ],

];
