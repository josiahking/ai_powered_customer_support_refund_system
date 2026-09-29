<?php

return [
    'ttl_minutes' => max(1, min(60, (int) env('ORDER_ACCESS_TTL_MINUTES', 15))),
];
