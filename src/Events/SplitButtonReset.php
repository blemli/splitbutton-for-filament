<?php

namespace Blemli\SplitButton\Events;

use Blemli\SplitButton\Layout;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A user reset one of their menus to the way the developer declared it.
 */
class SplitButtonReset extends SplitButtonEvent
{
    public function __construct(
        ?Authenticatable $user,
        string $key,
        public Layout $previous,
    ) {
        parent::__construct($user, $key);
    }
}
