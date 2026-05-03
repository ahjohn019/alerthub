<?php

return [
    'escalation' => [
        'threshold' => env('ALERTHUB_ESCALATION_THRESHOLD', 5),
        'window_minutes' => env('ALERTHUB_ESCALATION_WINDOW_MINUTES', 60),
    ],
];
