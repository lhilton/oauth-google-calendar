<?php
namespace Niisan\Laravel\GoogleCalendar\Models;

use Illuminate\Support\Carbon;

class Token
{
    public ?string $access_token;
    public ?string $refresh_token;
    public ?int $expires;

    public function __construct(array $params)
    {
        $this->access_token = $params['access_token'] ?? null;
        $this->refresh_token = $params['refresh_token'] ?? null;
        $this->expires = Carbon::now()->timestamp + $params['expires_in'];
    }
}