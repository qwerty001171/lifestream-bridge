<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('billing:sync-all')->everyFiveMinutes();
Schedule::command('lifestream:sync-all')->everyTenMinutes();
Schedule::command('billing:sync-passwords-all')->everyFifteenMinutes();
