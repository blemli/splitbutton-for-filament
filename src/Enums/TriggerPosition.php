<?php

namespace Blemli\SplitButton\Enums;

/**
 * Where the menu trigger sits relative to the button(s). Logical, not
 * physical: Start is the reading start (left in LTR, right in RTL).
 */
enum TriggerPosition: string
{
    case Start = 'start';

    case End = 'end';
}
