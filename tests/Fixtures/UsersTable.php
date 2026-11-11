<?php

namespace Blemli\SplitButton\Tests\Fixtures;

use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class UsersTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public static ?Closure $recordActions = null;

    public array $log = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query())
            ->columns([TextColumn::make('name')])
            ->recordActions(static::$recordActions ? (static::$recordActions)($this) : []);
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {{ $this->table }}

                <x-filament-actions::modals />
            </div>
        BLADE;
    }
}
