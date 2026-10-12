<?php

return [
    'bot_traffic' => [
        'abnormal_requests_per_hour' => max(1, (int) env('BOT_ABNORMAL_REQUESTS_PER_HOUR', 120)),
        'extra_user_agents' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BOT_EXTRA_USER_AGENTS', '')),
        ))),
    ],
];
