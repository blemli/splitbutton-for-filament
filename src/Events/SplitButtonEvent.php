<?php

namespace Blemli\SplitButton\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something a user did to one of their split button menus. The key says
 * which one: panel, the Livewire component the button lives on, and the
 * button (its first action's name, or its ->key()).
 */
abstract class SplitButtonEvent
{
    use Dispatchable;

    public function __construct(
        public ?Authenticatable $user,
        public string $key,
    ) {}

    public function panel(): string
    {
        return $this->part(0);
    }

    /**
     * The class of the page or component the button is on.
     */
    public function component(): string
    {
        return str_replace('.', '\\', $this->part(1));
    }

    public function button(): string
    {
        return $this->part(2);
    }

    protected function part(int $index): string
    {
        return explode('::', $this->key, 3)[$index] ?? '';
    }
}
