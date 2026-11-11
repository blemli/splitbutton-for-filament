<?php

namespace Blemli\SplitButton\Enums;

/**
 * Where the chevron sits relative to the button(s). Logical, not
 * physical: Start is the reading start (left in LTR, right in RTL).
 */
enum ChevronPosition: string
{
    case Start = 'start';

    case End = 'end';
}
