<?php

namespace Blemli\SplitButton\Enums;

/**
 * What a user just did to a menu in edit mode.
 */
enum Customization: string
{
    /** Moved an entry (drag, or Ctrl / ⌘ with the arrows). */
    case Reordered = 'reordered';

    case Hidden = 'hidden';

    case Shown = 'shown';

    /** Put an entry on the button. */
    case Pinned = 'pinned';

    case Unpinned = 'unpinned';

    /** Switched "follow my picks" on, off or to another strategy. */
    case AutoPin = 'auto_pin';
}
