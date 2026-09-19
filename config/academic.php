<?php

return [
    'enrollment_seed_count' => (int) env('ENROLLMENT_SEED_COUNT', 5_000_000),
    'enrollment_seed_chunk' => (int) env('ENROLLMENT_SEED_CHUNK', 5_000),
    'seed_academic_year' => env('ENROLLMENT_SEED_ACADEMIC_YEAR', '2026/2027'),
];
