<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('auctions:update-status')->everyMinute();
