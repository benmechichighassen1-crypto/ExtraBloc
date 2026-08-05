<?php

return [
    /*
     * La décision automatique est toujours révisable par la direction.
     * Les règles précises seront finalisées après validation métier.
     */
    'auto_prevalidation' => env('EXTRA_BLOC_AUTO_PREVALIDATION', true),
    'maximum_clock_gap_minutes' => env('EXTRA_BLOC_MAXIMUM_CLOCK_GAP_MINUTES', 30),

    'direction_groups' => array_filter(array_map(
        'trim',
        explode(',', env('EXTRA_BLOC_DIRECTION_GROUPS', 'DIRECTION,ADMINISTRATEUR')),
    )),
];
