<?php

return [
    'automation_enabled' => (bool) env('INSTAGRAM_AUTOMATION_ENABLED', false),
    'timezone' => env('INSTAGRAM_AUTOMATION_TIMEZONE', 'America/Argentina/Cordoba'),
    'max_attempts' => (int) env('INSTAGRAM_AUTOMATION_MAX_ATTEMPTS', 3),
    'processing_timeout_minutes' => (int) env('INSTAGRAM_AUTOMATION_PROCESSING_TIMEOUT_MINUTES', 20),
    'media_url_ttl_minutes' => (int) env('INSTAGRAM_AUTOMATION_MEDIA_URL_TTL_MINUTES', 90),
    'media_disk' => env('INSTAGRAM_AUTOMATION_MEDIA_DISK', 'local'),
    'public_media_base_url' => env('INSTAGRAM_AUTOMATION_PUBLIC_MEDIA_BASE_URL', env('APP_URL')),
    'n8n_token' => env('N8N_AUTOMATION_TOKEN'),
];
