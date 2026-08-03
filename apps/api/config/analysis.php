<?php

return [
    'stale_queue_minutes' => (int) env('ANALYSIS_STALE_QUEUE_MINUTES', 15),
    'log_retention_days' => (int) env('APPLICATION_LOG_RETENTION_DAYS', 90),
];
