<?php

return [
    'disk' => env('LESSON_VIDEO_DISK', 'local'),
    's3_acl' => env('LESSON_VIDEO_S3_ACL', 'bucket-owner-full-control'),
    'max_bytes' => 256 * 1024 * 1024,
    'chunk_bytes' => 512 * 1024,
    'maximum_duration_seconds' => 21600,
];
