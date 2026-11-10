<?php

return [
    'max_upload_kb' => (int) env('DOCUMENT_MAX_UPLOAD_KB', 20480),

    'allowed_mimes' => ['pdf', 'docx', 'doc'],
];
