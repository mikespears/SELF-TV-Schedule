<?php

declare(strict_types=1);

$pretalxHost = 'https://speakers.southeastlinuxfest.org';
$eventSlug = 'southeast-linux-fest-2026';

return [
    'pretalx_host' => $pretalxHost,
    'event_slug' => $eventSlug,
    'api_base' => rtrim($pretalxHost, '/') . '/api/events/' . $eventSlug,
    'timezone' => 'America/New_York',
    'locale' => 'en',
    'cache_ttl_seconds' => 300,
    'cache_dir' => __DIR__ . '/cache',
    'refresh_seconds' => 60,
    // Testing: set allow_test_clock true locally, then use ?now= or test_now below.
    'allow_test_clock' => false,
    'test_now' => null, // e.g. '2026-06-12T10:30:00' (America/New_York)
    'rooms' => [
        'salon-a' => [
            'id' => 13,
            'label' => 'Salon A',
            'subtitle' => 'Altispeed Ballroom',
        ],
        'salon-b' => [
            'id' => 14,
            'label' => 'Salon B',
            'subtitle' => 'Rocky Linux Ballroom',
        ],
        'salon-c-e' => [
            'id' => 15,
            'label' => 'Salon C-E',
            'subtitle' => 'VictoriaMetrics Ballroom',
        ],
        'piedmont' => [
            'id' => 16,
            'label' => 'Piedmont 1-3',
            'subtitle' => 'TBD Ballroom',
        ],
        'carolina' => [
            'id' => 17,
            'label' => 'Carolina Ballroom',
            'subtitle' => 'Lounge',
        ],
        'almalinux' => [
            'id' => 18,
            'label' => 'AlmaLinux Classroom',
            'subtitle' => '',
        ],
    ],
];
