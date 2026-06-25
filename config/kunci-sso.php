<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kunci SSO Configuration
    |--------------------------------------------------------------------------
    |
    | Di sini Anda dapat mengonfigurasi kredensial aplikasi klien Kunci SSO Anda.
    | Dapatkan client_id dan client_secret dari portal admin pusat.
    |
    */

    'client_id' => env('KUNCI_SSO_CLIENT_ID'),
    
    'client_secret' => env('KUNCI_SSO_CLIENT_SECRET'),
    
    'redirect' => env('KUNCI_SSO_REDIRECT_URI'),
    
    'central_url' => env('KUNCI_SSO_CENTRAL_URL', 'https://kunci.co.id'),
    
    'portal_url' => env('KUNCI_SSO_PORTAL_URL', 'https://kunci.co.id/portal'),
];
