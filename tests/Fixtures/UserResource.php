<?php

namespace Blemli\SplitButton\Tests\Fixtures;

use Blemli\SplitButton\Tests\Fixtures\UserResource\CreateUser;
use Blemli\SplitButton\Tests\Fixtures\UserResource\EditUser;
use Blemli\SplitButton\Tests\Fixtures\UserResource\ListUsers;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-user';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
