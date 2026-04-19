<?php

return [
    'analytics' => [
        // How fast a close counts as a "bounce" (dismissal in under N ms).
        'bounce_threshold_ms' => env('DASHED_POPUP_BOUNCE_MS', 2000),

        'thresholds' => [
            // higher is better
            'conversion_rate' => ['good' => 0.02, 'warn' => 0.005],
            // lower is better
            'bounce_rate' => ['good' => 0.20, 'warn' => 0.50],
            // lower is better
            'dismissal_rate' => ['good' => 0.40, 'warn' => 0.80],
            'min_views_for_confidence' => 100,
        ],

        // Days of sliding window recomputed every night.
        'rollup_days' => 7,

        // Today-cache TTL (seconds) for the on-demand path.
        'today_cache_seconds' => 300,

        // Refresh prompts in AI analysis after N days.
        'ai_stale_days' => 7,
    ],
];
