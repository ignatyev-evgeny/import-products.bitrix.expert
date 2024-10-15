<?php

use App\Console\Commands\updateAccessTokenCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command(updateAccessTokenCommand::class)->everyFifteenMinutes();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
