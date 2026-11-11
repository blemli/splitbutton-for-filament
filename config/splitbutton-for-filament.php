<?php

// Defaults for every SplitButton. Stored as plain strings so the published
// file loads on Filament 4 and 5 alike; fluent calls on a button always win.
return [

    // Table of the database layout store (only used with ->persist()).
    'table' => 'splitbutton_layouts',

    // How long (ms) the menu must be held for ->editableWithLongPress().
    'long_press_delay' => 500,

    // Days after which a pick counts half for AutoPin::Frecent ("most used lately").
    'frecency_half_life' => 14,

    // Where the chevron sits relative to the button(s): 'start' | 'end'.
    'chevron_position' => 'end',

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
