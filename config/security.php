<?php

return [
    'ai_crawler_protection' => [
        'extra_user_agents' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_CRAWLER_EXTRA_USER_AGENTS', '')),
        ))),
    ],
];
