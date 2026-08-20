<?php

return [
    'action_items' => [
        'due_soon_days' => (int) env('AMMS_ACTION_ITEMS_DUE_SOON_DAYS', 3),
        'evidence_max_size' => (int) env('AMMS_ACTION_ITEMS_EVIDENCE_MAX_SIZE', 10 * 1024 * 1024),
        'evidence_mimes' => array_values(array_filter(array_map('trim', explode(',', (string) env('AMMS_ACTION_ITEMS_EVIDENCE_MIMES', 'pdf,jpg,jpeg,png,doc,docx'))))),
    ],
];
