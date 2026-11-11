<?php

// Defaults for every SplitButton. Stored as plain strings so the published
// file loads on Filament 4 and 5 alike; fluent calls on a button always win.
return [

    // Table of the database layout store (only used with ->persist()).
    'table' => 'splitbutton_layouts',

    // How long (ms) the menu must be pressed to enter edit mode.
    'long_press' => 500,

    // Where the menu trigger sits relative to the button(s): 'start' | 'end'.
    'trigger_position' => 'end',

    // Drop actions the user is not authorized for instead of disabling them.
    'filter_unauthorized' => false,

    // The chevron of a split button.
    'split' => [
        'icon' => 'heroicon-m-chevron-down',
    ],

    // The "More" menu of ->overflow().
    'overflow' => [
        'primary_count' => 1,
        'label' => null, // null = "More", translated
        'icon' => 'heroicon-m-ellipsis-vertical',
        'color' => 'gray',
    ],

];
