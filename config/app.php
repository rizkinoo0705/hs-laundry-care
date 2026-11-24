<?php

// Hanya nilai yang berbeda dari bawaan Laravel; sisanya digabung otomatis oleh framework.
return [
    'name' => env('APP_NAME', 'HS Laundry & Care'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'locale' => env('APP_LOCALE', 'id'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'id_ID'),
];
