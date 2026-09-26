<?php

return [
    'refund_window_days' => (int) env('REFUND_WINDOW_DAYS', 30),
    'high_value_threshold_cents' => (int) env('REFUND_HIGH_VALUE_THRESHOLD_CENTS', 50_000),
];
