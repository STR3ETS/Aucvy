<?php

declare(strict_types=1);

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

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'mollie' => [
        'key' => env('MOLLIE_API_KEY'),
    ],

    /*
     | Mailgun (besluit Raph, 2 november 2026). Zet MAIL_MAILER=mailgun als
     | het account klaar is. Het EU-endpoint is de standaard, zodat mail
     | binnen de EU verwerkt wordt.
     */
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'),
        'scheme' => 'https',
        'webhook_signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),
    ],

    /*
     | De blog komt uit het CMS van Eazyonline (besluit Raph, 2 november
     | 2026), net als bij FitnessAannemer. Een leestoken volstaat; zonder
     | token toont /blog een nette lege staat.
     */
    'eazyonline' => [
        'base_url' => env('EAZYONLINE_API_URL', 'https://eazyonline.nl/api/v1'),
        'token' => env('EAZYONLINE_API_TOKEN'),
        'cache_minutes' => (int) env('EAZYONLINE_CACHE_MINUTES', 15),
    ],

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
     | Kanalen voor verspreiden (briefing nieuwe functies). Zonder sleutels
     | toont /account/koppelingen "Koppeling volgt" en dekt de deelkit het
     | kanaal. Facebook en Instagram delen de Meta-app; de app-review bij
     | Meta duurt één tot drie weken.
     */
    'facebook' => [
        'client_id' => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        'authorize_url' => 'https://www.facebook.com/v21.0/dialog/oauth',
        'token_url' => 'https://graph.facebook.com/v21.0/oauth/access_token',
        'scope' => 'pages_manage_posts,pages_read_engagement',
    ],

    'instagram' => [
        'client_id' => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        'authorize_url' => 'https://www.facebook.com/v21.0/dialog/oauth',
        'token_url' => 'https://graph.facebook.com/v21.0/oauth/access_token',
        'scope' => 'instagram_basic,instagram_content_publish,pages_show_list',
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'authorize_url' => 'https://www.linkedin.com/oauth/v2/authorization',
        'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
        'scope' => 'openid profile w_member_social',
    ],

    'marktplaats' => [
        // Alleen als API-partner (voorwaarden art. 7.4); tot die tijd via de deelkit.
        'client_id' => env('MARKTPLAATS_CLIENT_ID'),
        'client_secret' => env('MARKTPLAATS_CLIENT_SECRET'),
        'authorize_url' => env('MARKTPLAATS_AUTHORIZE_URL'),
        'token_url' => env('MARKTPLAATS_TOKEN_URL'),
        'scope' => '',
    ],

];
