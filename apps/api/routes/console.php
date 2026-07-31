<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('queue:prune-batches --hours=168')->daily();
Schedule::command('model:prune')->dailyAt('02:20');
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
