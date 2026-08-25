<?php

return [
    'ttl_hours' => env('DOWNLOAD_TTL_HOURS', 24),
    'max_bytes' => env('DOWNLOAD_MAX_BYTES', 250 * 1024 * 1024), // 250MB
    'process_timeout' => env('DOWNLOAD_PROCESS_TIMEOUT', 1800),
    'storage_path' => 'downloads', // inside storage/app/
    'yt_dlp_python_path' => env('YT_DLP_PYTHON_PATH'),
    'ffmpeg_path' => env('FFMPEG_PATH'),

    'youtube' => [
        'enabled' => true,
        'bin_path' => env('YOUTUBE_DL_PATH', 'yt-dlp'),
    ],

    'tiktok' => [
        'enabled' => true,
        'bin_path' => env('TIKTOK_DL_PATH', env('YOUTUBE_DL_PATH', 'yt-dlp')),
    ],

    'direct' => [
        'enabled' => true,
        'blocked_ips' => [
            '127.0.0.0/8',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '0.0.0.0/8',
            '169.254.0.0/16',
            '::1/128',
            'fc00::/7',
            'fe80::/10',
        ],
    ],
];
