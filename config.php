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
    // Gold sponsors from https://southeastlinuxfest.org/about/sponsors/
    'gold_sponsors' => [
        [
            'name' => 'VictoriaMetrics',
            'logo' => 'https://southeastlinuxfest.org/wp-content/uploads/2025/05/vm-ver-asset-purple3-600x371.jpg',
            'url' => 'https://victoriametrics.com/',
        ],
        [
            'name' => 'Rocky Linux',
            'logo' => 'https://southeastlinuxfest.org/wp-content/uploads/2022/06/rockylinuxlogo-600x228.png',
            'url' => 'https://rockylinux.org/',
        ],
        [
            'name' => 'Altispeed',
            'logo' => 'https://southeastlinuxfest.org/wp-content/uploads/2019/06/Altispeed-600x250.jpg',
            'url' => 'https://www.altispeed.com/',
        ],
    ],
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
