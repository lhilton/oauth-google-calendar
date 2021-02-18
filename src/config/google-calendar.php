<?php
return [
    'scopes' => [
        'profile',
        'email',
        'https://www.googleapis.com/auth/calendar.events',
    ],
    'redirect' => env('GOOGLE_CALENDAR_OAUTH_REDIRECT_URL', ''),
    'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET', ''),
    'events' => [
        'token_refreshed' => ''
    ]
];