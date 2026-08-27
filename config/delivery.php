<?php

return [
    /*
    | Route matrix provider: osrm | google | mapbox | openrouteservice | haversine
    */
    'provider' => env('DELIVERY_ROUTING_PROVIDER', 'osrm'),

    'osrm' => [
        'base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org'),
    ],

    'google' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'mapbox' => [
        'token' => env('MAPBOX_ACCESS_TOKEN'),
    ],

    'openrouteservice' => [
        'key' => env('OPENROUTESERVICE_API_KEY'),
        'base_url' => env('OPENROUTESERVICE_BASE_URL', 'https://api.openrouteservice.org'),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
    ],
];
