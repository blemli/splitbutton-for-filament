<?php

namespace Blemli\SplitButton\Tests\Fixtures;

use Blemli\SplitButton\Concerns\InteractsWithSplitButtonActions;
use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

/**
 * A custom Livewire component that draws its own split button — no page
 * header, no table, no schema.
 */
class SplitHost extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithSplitButtonActions;

    public static ?Closure $buttons = null;

    public array $log = [];

    protected function getSplitButtons(): array
    {
        return static::$buttons ? (static::$buttons)($this) : [];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @foreach ($this->getCachedSplitButtons() as $button)
                    {{ $button }}
                @endforeach

                <x-filament-actions::modals />
            </div>
        BLADE;
    }
}
