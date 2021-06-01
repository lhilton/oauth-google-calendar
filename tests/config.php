<?php

class DummyConfig
{
    private static $config = [
        'google-calendar.redirect' => 'http://example.com',
        'google-calendar.scopes' => [
            'abcd', 'efgh'
        ],
        'google-calendar.events.token_refreshed' => '',
        'google-calendar.holiday_id' => 'holiday_id',
        'app.timezone' => 'Asia/Tokyo'
    ];

    public static function get($name)
    {
        return self::$config[$name];
    }

    public static function set($name, $value)
    {
        self::$config[$name] = $value;
    }
}

function config($name) {
    return DummyConfig::get($name);
}

function config_set($name, $value) {
    DummyConfig::set($name, $value);
}