<?php
namespace Test;
require_once __DIR__ . '/config.php';

use Google\Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_Events;
use Google_Service_Calendar_Resource_Events;
use Google_Service_Oauth2;
use Google_Service_Oauth2_Resource_Userinfo;
use Google_Service_Oauth2_Userinfo;
use Illuminate\Support\Carbon;
use Mockery;
use Niisan\Laravel\GoogleCalendar\Models\Token;
use Niisan\Laravel\GoogleCalendar\OauthCalendarService;
use PHPUnit\Framework\TestCase;

class OauthCalendarServiceTest extends TestCase
{
    private $service;
    private $client;
    private $oauth;
    private $calendar;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(Client::class);
        $this->oauth = Mockery::mock(Google_Service_Oauth2_Resource_Userinfo::class);
        $this->calendar = Mockery::mock(Google_Service_Calendar_Resource_Events::class);
        $oauth = new Google_Service_Oauth2($this->client);
        $oauth->userinfo = $this->oauth;
        $calendar = new Google_Service_Calendar($this->client);
        $calendar->events = $this->calendar;
        $this->service = new OauthCalendarService($this->client, $oauth, $calendar);
    }

    public function test_getAuthUri()
    {
        $this->client->allows()->setRedirectUri('https://example.com/callback');
        $this->client->allows()->setAccessType('offline');
        $this->client->allows()->setApprovalPrompt('auto');
        $this->client->allows()->addScope('abcd');
        $this->client->allows()->addScope('efgh');
        $this->client->allows()->createAuthUrl()->andReturns('https://example.com/oauth');
        $this->assertEquals('https://example.com/oauth', $this->service->getAuthUri('https://example.com/callback'));
    }

    public function test_getTokenByCode()
    {
        Carbon::setTestNow(Carbon::createFromTimestamp(1613575932));
        $this->client->allows()->fetchAccessTokenWithAuthCode('abcdefg')->andReturns([
            'access_token' => 'ljljdes',
            'refresh_token' => 'qwsdft',
            'expires_in' => 3600
        ]);

        $token = $this->service->getTokenByCode('abcdefg');

        $this->assertEquals('ljljdes', $token->access_token);
        $this->assertEquals('qwsdft', $token->refresh_token);
        $this->assertEquals(1613579532, $token->expires);
    }

    public function test_getUserInfo()
    {
        Carbon::setTestNow(Carbon::createFromTimestamp(1613575932));
        $token = new Token([
            'access_token' => 'ljljdes',
            'refresh_token' => 'qwsdft',
            'expires_in' => 3600
        ]);
        $this->client->allows()->setAccessToken('ljljdes');
        Carbon::setTestNow(Carbon::createFromTimestamp(1613595932));
        $this->client->expects()->fetchAccessTokenWithRefreshToken('qwsdft')->andReturn([
            'access_token' => 'ljljdesfad',
            'refresh_token' => 'qwsdftfdad',
            'expires_in' => 3600
        ]);
        $this->oauth->allows()->get()->andReturn(new Google_Service_Oauth2_Userinfo([
            'email' => 'test@example.com'
        ]));

        $ret = $this->service->getUserInfo($token);
        $this->assertEquals('test@example.com', $ret->email);
    }

    public function test_getClient()
    {
        $this->assertEquals($this->client, $this->service->getClient());
    }

    public function test_getEventList()
    {
        $event1 = new Google_Service_Calendar_Event;
        $event2 = new Google_Service_Calendar_Event;
        $list1 = new Google_Service_Calendar_Events;
        $list1->setItems([$event1, $event2]);
        $list1->setNextPageToken('abcdefghi');
        $event3 = new Google_Service_Calendar_Event;
        $list2 = new Google_Service_Calendar_Events;
        $list2->setItems([$event3]);

        $this->calendar->allows()->listEvents('primary', [
                'timeMax' => '2021-02-01T12:00:00+09:00',
                'timeMin' => '2021-01-01T12:00:00+09:00'
        ])->andReturn($list1);
        $this->calendar->allows()->listEvents('primary', [
                'timeMin' => '2021-01-01T12:00:00+09:00',
                'timeMax' => '2021-02-01T12:00:00+09:00',
                'pageToken' => 'abcdefghi'
        ])->andReturn($list2);

        Carbon::setTestNow(Carbon::createFromTimestamp(1613575932));
        $token = new Token([
            'access_token' => 'ljljaes',
            'refresh_token' => 'qwsdft',
            'expires_in' => 3600
        ]);
        date_default_timezone_set('Asia/Tokyo');
        $this->client->allows()->setAccessToken('ljljaes');

        $list = $this->service->getEventList($token, ['timeMin' => '2021-01-01 12:00:00', 'timeMax' => '2021-02-01 12:00:00']);

        $this->assertEquals($event1, $list[0]);
        $this->assertEquals($event2, $list[1]);
        $this->assertEquals($event3, $list[2]);
    }

    public function test_createEvent()
    {
        Carbon::setTestNow(Carbon::createFromTimestamp(1613575932));
        $token = new Token([
            'access_token' => 'ljljdes',
            'refresh_token' => 'qwsdft',
            'expires_in' => 3600
        ]);
        $this->client->allows()->setAccessToken('ljljdes');
        $this->calendar->shouldReceive('insert')->andReturnUsing(function($a, $b) {
            return $b;
        });
        date_default_timezone_set('Asia/Tokyo');

        $event = $this->service->createEvent($token, [
            'summary' => 'abcd',
            'start' => '2021-01-01 12:00:00',
            'end' => '2021-01-01 13:00:00'
        ]);

        $this->assertEquals('abcd', $event->getSummary());
        $this->assertEquals('2021-01-01T12:00:00+09:00', $event->start->dateTime);
        $this->assertEquals('2021-01-01T13:00:00+09:00', $event->end->dateTime);
    }

    public function test_deleteEvent()
    {
        Carbon::setTestNow(Carbon::createFromTimestamp(1613575932));
        $token = new Token([
            'access_token' => 'ljljdes',
            'refresh_token' => 'qwsdft',
            'expires_in' => 3600
        ]);
        $this->client->allows()->setAccessToken('ljljdes');

        $this->calendar->expects()->delete('primary', '1212');
        $this->service->deleteEvent($token, '1212');
        $this->assertTrue(true);
    }

    public function test_dispatchEvent()
    {
        Carbon::setTestNow(Carbon::createFromTimestamp(1613575932));
        $token = new Token([
            'access_token' => 'ljljdes',
            'refresh_token' => 'qwsdft',
            'expires_in' => 3600
        ]);
        $this->client->allows()->setAccessToken('ljljdes');
        Carbon::setTestNow(Carbon::createFromTimestamp(1613595932));
        $this->client->expects()->fetchAccessTokenWithRefreshToken('qwsdft')->andReturn([
            'access_token' => 'ljljdesfad',
            'refresh_token' => 'qwsdftfdad',
            'expires_in' => 3600
        ]);
        $this->calendar->expects()->delete('primary', '1212');
        config_set('google-calendar.events.token_refreshed', TestDispatcher::class);

        $this->service->deleteEvent($token, '1212');

        $obj = TestDispatcher::$obj;

        $this->assertEquals($token, $obj->user);
        $this->assertEquals([
            'access_token' => 'ljljdesfad',
            'refresh_token' => 'qwsdftfdad',
            'expires_in' => 3600,
            'expires' => 1613599532
        ], $obj->param);

    }

    public function tearDown(): void
    {
        Mockery::close();
    }
}

class TestDispatcher
{
    public $user;
    public $param;

    public static $obj;

    public static function dispatch($user, $param)
    {
        self::$obj = new self($user, $param);
    }

    public function __construct($user, $param)
    {
        $this->user = $user;
        $this->param = $param;
    }
}