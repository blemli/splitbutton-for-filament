<?php

namespace Blemli\SplitButton\Tests\Fixtures;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

/**
 * A bare Livewire component with a form, an infolist and one action that
 * opens a modal — the places besides pages and tables where Filament takes
 * an action group.
 */
class SchemaHost extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public static ?Closure $form = null;

    public static ?Closure $infolist = null;

    public static ?Closure $open = null;

    public array $log = [];

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(static::$form ? (static::$form)($this) : []);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->state(['name' => 'Ada'])
            ->components(static::$infolist ? (static::$infolist)($this) : []);
    }

    public function openAction(): Action
    {
        return static::$open ? (static::$open)($this) : Action::make('open');
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {{ $this->form }}

                {{ $this->infolist }}

                {{ $this->openAction }}

                <x-filament-actions::modals />
            </div>
        BLADE;
    }
}
