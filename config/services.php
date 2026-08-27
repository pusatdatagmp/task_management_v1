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

    // F-184/F-185: Firebase Cloud Messaging -- supersede F-6 ("Firebase ditunda
    // v3.0"), permintaan Boss dimajukan sekarang. `enabled` default FALSE --
    // via() di TaskNotification/MentionNotification/TemplateBlockedNotification
    // cuma tambah FcmChannel::class kalau flag ini TRUE, jadi aman di-deploy
    // sebelum kredensial Boss terpasang (nol perubahan ke channel `database`
    // yang sudah ada). `credentials` = path file Service Account JSON (Firebase
    // Console > Project Settings > Service Accounts), JANGAN PERNAH commit file
    // itu ke git (taruh di storage/app/firebase/, sudah di .gitignore). `web` =
    // config publik Firebase Web SDK (apiKey dkk) -- AMAN diekspos ke browser,
    // Firebase security-nya bukan dari menyembunyikan nilai ini (beda dari
    // 'credentials' di atas yang WAJIB rahasia).
    'fcm' => [
        'enabled' => (bool) env('FCM_ENABLED', false),
        'credentials' => env('FCM_SERVICE_ACCOUNT_PATH', storage_path('app/firebase/service-account.json')),
        'vapid_public_key' => env('FIREBASE_VAPID_PUBLIC_KEY'),
        'web' => [
            'api_key' => env('FIREBASE_API_KEY'),
            'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
            'project_id' => env('FIREBASE_PROJECT_ID'),
            'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
            'app_id' => env('FIREBASE_APP_ID'),
        ],
    ],

];
