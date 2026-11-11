<?php

namespace Blemli\SplitButton\Tests\Fixtures\UserResource;

use Blemli\SplitButton\Tests\Fixtures\UserResource;
use Closure;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public static ?Closure $headerActions = null;

    protected function getHeaderActions(): array
    {
        return static::$headerActions ? (static::$headerActions)($this) : [];
    }
}
