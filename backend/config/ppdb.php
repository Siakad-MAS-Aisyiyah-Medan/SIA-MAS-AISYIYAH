<?php

return [
    'berkas' => [
        'max_size_kb' => (int) env('PPDB_BERKAS_MAX_KB', 2048),
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'allowed_mimes' => [
            'application/pdf',
            'application/x-pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
        ],
        'storage_disk' => 'local',
        'storage_path_prefix' => 'ppdb',
    ],
];
