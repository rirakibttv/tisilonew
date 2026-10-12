<?php

return [
    'bot_traffic' => [
        'extra_user_agents' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BOT_EXTRA_USER_AGENTS', '')),
        ))),
    ],
];
