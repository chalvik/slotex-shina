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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'bitrix24' => [
        'webhook_add_url' => env('BITRIX24_WEBHOOK_ADD_URL'),
        'webhook_update_url' => env('BITRIX24_WEBHOOK_UPDATE_URL'),
        'webhook_list_url' => env('BITRIX24_WEBHOOK_LIST_URL'),
        'webhook_deal_add_url' => env('BITRIX24_WEBHOOK_DEAL_ADD_URL'),
        'webhook_deal_update_url' => env('BITRIX24_WEBHOOK_DEAL_UPDATE_URL'),
        'webhook_deal_list_url' => env('BITRIX24_WEBHOOK_DEAL_LIST_URL'),
        'webhook_user_get_url' => env('BITRIX24_WEBHOOK_USER_GET_URL'),
        'webhook_status_list_url' => env('BITRIX24_WEBHOOK_STATUS_LIST_URL'),
        'webhook_deal_fields_url' => env('BITRIX24_WEBHOOK_DEAL_FIELDS_URL'),
        'webhook_contact_list_url' => env('BITRIX24_WEBHOOK_CONTACT_LIST_URL'),
    ],

    'roistat' => [
        'webhook_secret' => env('ROISTAT_WEBHOOK_SECRET'),
        'export_user' => env('ROISTAT_EXPORT_USER'),
        'export_password' => env('ROISTAT_EXPORT_PASSWORD'),
    ],

];
