<?php
namespace Niisan\Laravel\GoogleCalendar;

use Carbon\Carbon;
use Google\Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Oauth2;
use Google_Service_Oauth2_Userinfo;
use Niisan\Laravel\GoogleCalendar\Models\Token;
use RuntimeException;

class OauthCalendarService
{

    /** Client $client google api client */
    private Client $client;
    private Google_Service_Oauth2 $oauth_service;
    private Google_Service_Calendar $calendar_service;

    /** array $config */
    private $config;

    /** string $access_token */
    private $access_token;

    /** string $refresh_token */
    private $refresh_token;

    protected $functionsWhenTokenRefreshed = [];

    public function __construct(
        Client $client,
        Google_Service_Oauth2 $oauth_service,
        Google_Service_Calendar $calendar_service
    ) {
        $this->client = $client;
        $this->oauth_service = $oauth_service;
        $this->calendar_service = $calendar_service;
    }

    /**
     * Get Oauth authorize url.
     *
     * @param string $redirect
     * @param bool   $forece_approval_prompt true: user must authorize prompt
     * @return string
     */
    public function getAuthUri(string $redirect = null, bool $forece_approval_prompt = false): string
    {
        $redirect = $redirect ?? config('google-calendar.redirect');
        $this->client->setRedirectUri($redirect);
        $this->client->setAccessType('offline');
        $this->client->setApprovalPrompt(($forece_approval_prompt) ? 'force': 'auto');
        foreach (config('google-calendar.scopes') as $scope) {
            $this->client->addScope($scope);
        }
        return $this->client->createAuthUrl();
    }

    /**
     * fetch token by code.
     *
     * @param string $code
     * @return Token
     */
    public function getTokenByCode(string $code): Token
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        $this->checkTokenError($token);
        return new Token($token);
    }

    /**
     * get user info
     * require scope: email, profile, openId
     *
     * @return Google_Service_Oauth2_Userinfo
     */
    public function getUserInfo($user): Google_Service_Oauth2_Userinfo
    {
        $this->setAccessToken($user);
        return $this->oauth_service->userinfo->get();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * get event list
     * 
     * @param mixed $user
     * 
     * @return Google_Service_Calendar_Event[]
     */
    public function getEventList($user, $config): array
    {
        $this->setAccessToken($user);
        $option = $this->setEventSearchConfig($config);
        $events = $this->calendar_service->events->listEvents('primary', $option);
        $ret = [];

        while(true) {
            foreach ($events->getItems() as $event) {
                $ret[] = $event;
            }
            $pageToken = $events->getNextPageToken();
            if ($pageToken) {
                $option['pageToken'] = $pageToken;
                $events = $this->calendar_service->events->listEvents('primary', $option);
            } else {
                break;
            }
        }
        return $ret;
    }

    /**
     * create event
     *
     * @param $user
     * @param array $data
     * @return Google_Service_Calendar_Event
     */
    public function createEvent($user, array $data): Google_Service_Calendar_Event
    {
        $event = new Google_Service_Calendar_Event([
            'summary' => $data['summary'],
            'start' => [
                'dateTime' => Carbon::parse($data['start'])->format(DATE_RFC3339)
            ],
            'end' => [
                'dateTime' => Carbon::parse($data['end'])->format(DATE_RFC3339)
            ]
        ]);
        $this->setAccessToken($user);
        $new_event = $this->calendar_service->events->insert('primary', $event);
        return $new_event;
    }

    /**
     * delete event
     *
     * @param $user
     * @param string $event_id
     * @return mixed
     */
    public function deleteEvent($user, $event_id)
    {
        $this->setAccessToken($user);
        return $this->calendar_service->events->delete('primary', $event_id);
    }

    /**
     * set access token.
     * 
     * @param $user
     * 
     * @return void
     */
    private function setAccessToken($user):void
    {
        $this->checkUser($user);
        if (Carbon::now()->timestamp >= $user->expires - 30) {
            $token = $this->client->fetchAccessTokenWithRefreshToken($user->refresh_token);
            $token['expires'] = Carbon::now()->timestamp + $token['expires_in'];
            if (config('google-calendar.events.token_refreshed')) {
                config('google-calendar.events.token_refreshed')::dispatch($user, $token);
            }
        }
    }

    /**
     * check valid user
     *
     * @param [type] $user
     * @return void
     */
    private function checkUser($user): void
    {
        foreach (['access_token', 'refresh_token', 'expires'] as $field) {
            if ($user->$field === null) {
                throw new \Exception('$user must have a field: ' . $field);
            }
        }
    }

    /**
     * set event search config
     *
     * @param array $config
     * @return array
     */
    private function setEventSearchConfig(array $config): array
    {
        $ret = [
            'orderBy' => $config['orderBy'] ?? null,
            'q' => $config['search'] ?? null,
            'timeMax' => (isset($config['timeMax'])) ? Carbon::parse($config['timeMax'])->format(DATE_RFC3339): null,
            'timeMin' => (isset($config['timeMin'])) ? Carbon::parse($config['timeMin'])->format(DATE_RFC3339): null,
            'timeZone' => $config['timeZone'] ?? null,
            'updatedMin' => (isset($config['updatedMin'])) ? Carbon::parse($config['updatedMin'])->format(DATE_RFC3339): null,
            'maxResults' => $config['maxResults'] ?? null,
        ];

        foreach (array_keys($ret) as $key) {
            if ($ret[$key] === null) {
                unset($ret[$key]);
            }
        }

        return $ret;
    }

    private function checkTokenError(array $response)
    {
        if (isset($response['error'])) {
            $err = 'Return Error Message: ';
            foreach ($response as $key => $val) {
                $err .= "$key: $val, ";
            }
            throw new RuntimeException($err);
        }
    }
}