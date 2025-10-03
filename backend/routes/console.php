<?php

use App\Jobs\AggregateLoginDailies;
use Illuminate\Support\Facades\Schedule;

Schedule::command('telescope:prune --hours=48')->daily();
Schedule::job(new AggregateLoginDailies())->dailyAt('00:30');