<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('agents:run-scheduled')->everyMinute();
