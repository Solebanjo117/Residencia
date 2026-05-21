<?php

return [
    'enabled' => env('ONLYOFFICE_ENABLED', false),

    'document_server_url' => rtrim((string) env('ONLYOFFICE_DOCUMENT_SERVER_URL', ''), '/'),

    'signed_url_ttl_minutes' => (int) env('ONLYOFFICE_SIGNED_URL_TTL_MINUTES', 120),

    'request_timeout_seconds' => (int) env('ONLYOFFICE_REQUEST_TIMEOUT_SECONDS', 30),
];
