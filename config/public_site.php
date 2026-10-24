<?php

return [
    // The storefront stays behind the coming-soon screen in production until launch sets PUBLIC_SITE_OPEN=true.
    'open' => (bool) env('PUBLIC_SITE_OPEN', env('APP_ENV', 'production') !== 'production'),
];
