<?php

return [
    'coins_per_tap' => (int) env('TAPTOEARN_COINS_PER_TAP', 1),
    'max_taps_per_request' => (int) env('TAPTOEARN_MAX_TAPS_PER_REQUEST', 50),
    'max_taps_per_minute' => (int) env('TAPTOEARN_MAX_TAPS_PER_MINUTE', 600),
    'max_taps_per_5_seconds' => (int) env('TAPTOEARN_MAX_TAPS_PER_5_SECONDS', 80),
    'init_data_ttl_seconds' => (int) env('TAPTOEARN_INIT_DATA_TTL_SECONDS', 86400),
    'allow_unverified_demo' => filter_var(
        env('TAPTOEARN_ALLOW_UNVERIFIED_DEMO', false),
        FILTER_VALIDATE_BOOL
    ),
];
