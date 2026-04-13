<?php

return [
    'upload' => [
        // Single source of truth for evidence file upload formats.
        'allowed_extensions' => ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => [
            'pdf' => ['application/pdf'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ],
        'max_kb' => 15360,
        'max_filename_chars' => 120,
    ],
];
