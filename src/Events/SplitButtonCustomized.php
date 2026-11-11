<?php

namespace Blemli\SplitButton\Events;

use Blemli\SplitButton\Enums\Customization;
use Blemli\SplitButton\Layout;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A user changed one of their menus in edit mode: moved, hid, showed or
 * pinned an entry, or switched "follow my picks". Fired once per change,
 * after it is stored.
 */
class SplitButtonCustomized extends SplitButtonEvent
{
    /**
     * @param  Customization | null  $change  what they did, as their browser reported it
     * @param  string | null  $entry  the entry it was done to (for AutoPin: the strategy, null when switched off)
     */
    public function __construct(
        ?Authenticatable $user,
        string $key,
        public Layout $layout,
        public Layout $previous,
        public ?Customization $change = null,
        public ?string $entry = null,
    ) {
        parent::__construct($user, $key);
    }

    public function is(Customization $change): bool
    {
        return $this->change === $change;
    }
}
