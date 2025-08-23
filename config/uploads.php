<?php

return [
    'image' => [
        // 10MB in KB
        'max_kb' => 10240,
        // Limit to jpeg/jpg/png to match FE
        'mimes' => ['jpeg', 'jpg', 'png'],
    ],
    'quotation' => [
        // 20MB in KB
        'max_kb' => 20480,
        // Match FE allowed types
        'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
    ],
];
