<?php
namespace Niisan\Laravel\GoogleCalendar;

use Google\Client;
use Google_Service_Calendar;
use Google_Service_Oauth2;
use Illuminate\Support\ServiceProvider;

class OauthCalendarServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->singleton(OauthCalendarService::class, function () {
            $client = new Client([
                'client_id' => config('google-calendar.client_id'),
                'client_secret' => config('google-calendar.client_secret')
            ]);
            $oauth_service = new Google_Service_Oauth2($client);
            $calendar_service = new Google_Service_Calendar($client);
            return new OauthCalendarService($client, $oauth_service, $calendar_service);
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/google-calendar.php' => config_path('google-calendar.php'),
        ]);
    }
}