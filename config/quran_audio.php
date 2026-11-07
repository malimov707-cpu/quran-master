<?php

return [
    'provider' => 'alquran_cloud',
    'source_url' => 'https://alquran.cloud/cdn',
    'terms_url' => 'https://alquran.cloud/terms-and-conditions',
    'terms_version' => '2026-06-14',
    'license' => 'Provider permission for personal and educational streaming/download; reciter copyright retained; removal requests must be honored.',
    'cdn' => 'https://cdn.islamic.network/quran',
    'editions' => [
        'ar.alafasy' => ['name' => 'Mishary Rashid Alafasy', 'style' => 'Murattal', 'bitrate' => 128],
        'ar.abdulbasitmurattal' => ['name' => 'Abdul Basit Abdus Samad', 'style' => 'Murattal', 'bitrate' => 192],
        'ar.mahermuaiqly' => ['name' => 'Maher Al-Muaiqly', 'style' => 'Murattal', 'bitrate' => 128],
        'ar.abdurrahmaansudais' => ['name' => 'Abdul Rahman Al-Sudais', 'style' => 'Murattal', 'bitrate' => 192],
        'ar.husary' => ['name' => 'Mahmoud Khalil Al-Husary', 'style' => 'Murattal', 'bitrate' => 64],
        'ar.minshawi' => ['name' => 'Muhammad Siddiq Al-Minshawi', 'style' => 'Murattal', 'bitrate' => 128],
        'ar.minshawimujawwad' => ['name' => 'Muhammad Siddiq Al-Minshawi', 'style' => 'Mujawwad', 'bitrate' => 64],
        'ar.yasserdossari' => [
            'provider' => 'mp3quran',
            'name' => 'Yasser Al-Dosari',
            'style' => 'Murattal',
            'bitrate' => 128,
            'surah_base_url' => 'https://cdn.mp3quran.net/audio/yasser-dosari/r1/',
            'source_url' => 'https://mp3quran.net/api/v3/reciters?language=eng',
            'license_url' => 'https://mp3quran.net',
        ],
    ],
];
