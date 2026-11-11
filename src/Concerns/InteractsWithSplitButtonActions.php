<?php

namespace Blemli\SplitButton\Concerns;

use Blemli\SplitButton\SplitButton;

/**
 * Split buttons on your own Livewire component (a widget, a custom page
 * section): Filament resolves single actions from `fooAction()` methods,
 * but not groups. Return them from getSplitButtons() instead and draw one
 * with {{ $this->getSplitButton('name') }}.
 *
 * Needs Filament's InteractsWithActions on the same component, which
 * calls cacheInteractsWithSplitButtonActions() on every request (it does so
 * for every trait whose name ends in "Actions").
 */
trait InteractsWithSplitButtonActions
{
    /** @var array<string, SplitButton> */
    protected array $cachedSplitButtons = [];

    public function cacheInteractsWithSplitButtonActions(): void
    {
        $this->cachedSplitButtons = [];

        foreach ($this->getSplitButtons() as $name => $button) {
            $button->livewire($this);

            // Registers every entry, closure entries included, so they
            // resolve when clicked.
            $this->mergeCachedActions($button->getFlatActions());

            $this->cachedSplitButtons[(string) $name] = $button;
        }
    }

    /**
     * @return array<string, SplitButton>
     */
    protected function getSplitButtons(): array
    {
        return [];
    }

    public function getSplitButton(string $name): ?SplitButton
    {
        return $this->cachedSplitButtons[$name] ?? null;
    }

    /**
     * @return array<string, SplitButton>
     */
    public function getCachedSplitButtons(): array
    {
        return $this->cachedSplitButtons;
    }
}
