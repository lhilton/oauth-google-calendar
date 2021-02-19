# oauth-google-calendar

This is a package of a series of processes to create an application linked to Google Calendar using OAuth authentication in Laravel.

## Required

Laravel >= 5.8
PHP >= 7.4

## Install
Install via composer.

```
composer require niisan/laravel-oauth-google-calendar
```

Then, bring the config to your config dir.

```
php artisan vendor:publish
```
and choose 'Niisan\Laravel\GoogleCalendar\OauthCalendarServiceProvider'.

You can use this package via DI container.

```php
    private OauthCalendarService $oauthCalendarService;

    public function __construct(OauthCalendarService $oauthCalendarService)
    {
        $this->oauthCalendarService = $oauthCalendarService;
    }
```

or

```php
    $service = app(OauthCalendarService::class);
```

## Config
This package's config file is the `google-calendar.php`. 

```php
<?php
return [
    'scopes' => [
        'profile',
        'email',
        'https://www.googleapis.com/auth/calendar.events',
    ],
    'redirect' => env('GOOGLE_CALENDAR_OAUTH_REDIRECT_URL', 'https://example.com/auth/callback'),
    'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET', ''),
    'events' => [
        'token_refreshed' => ''
    ]
];
```
`client_id` and `client_secret` are from Google OAuth user. 
`events.token_refreshed` can define a event when token refreshed in this package.

For example,

```php
'events' => [
    'token_refreshed' => TokenRefreshedEvent::class
]
```

And, `Events/TokenRefreshedEvent.php`

```php
class TokenRefreshedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, array $token)
    {
        //
    }
```

## Usage
This package has following methods.

```php
public function getEventList($user, $config): array;
public function createEvent($user, array $data): Google_Service_Calendar_Event;
public function deleteEvent($user, $event_id);
```
These methods take `$user` as an argument. `$user` is a object and must huve accessible propeties, `access_token`, `refresh_token`, `expires`.

### getEventList()
This method get user's event. `$config` is a search condition.
```php
$config = [
    'orderBy' => null,
    'search' => 'test',
    'timeMax' =>'2021-01-01',
    'timeMin' => '2020-12-01',
    'timeZone' => 'Asia/Tokyo',
    'updatedMin' => null
    'maxResults' => 150,
];
```

### createEvent
This method create user's event. `$data` is a event content.

```php
$data = [
    'summary' => 'abcd',
    'start' => '2021-02-21 12:00:00',
    'end' => '2021-02-21 13:00:00'
];
```