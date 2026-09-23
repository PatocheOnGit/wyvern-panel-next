<?php

return [

    'name' => env('APP_NAME', 'Wyvern'),
    'logo' => env('APP_LOGO'),
    'favicon' => env('APP_FAVICON', '/wyvern/mark.svg'),

    'version' => '0.3.3',

    'timezone' => 'UTC',

    'installed' => env('APP_INSTALLED', true),

    'exceptions' => [
        'report_all' => env('APP_REPORT_ALL_EXCEPTIONS', false),
    ],

    'fallback_locale' => 'en',

];
