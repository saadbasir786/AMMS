<?php

return [
    'minimum_active_fingers' => (int) env('BIOMETRIC_MINIMUM_ACTIVE_FINGERS', 2),
    'enrollment_session_ttl_minutes' => (int) env('BIOMETRIC_ENROLLMENT_SESSION_TTL_MINUTES', 15),
    'device_driver' => env('BIOMETRIC_DEVICE_DRIVER', 'none'),
];
