<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Restaurant
    |--------------------------------------------------------------------------
    |
    | Every business row belongs to a restaurant. Until per-request tenant
    | resolution exists, all requests, jobs and commands run as this one.
    |
    */

    'default_restaurant_id' => (int) env('TENANCY_DEFAULT_RESTAURANT_ID', 1),

];
