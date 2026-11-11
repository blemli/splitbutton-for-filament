<?php

namespace Blemli\SplitButton\Tests\Fixtures;

use Closure;
use Filament\Pages\Page;

class TestPage extends Page
{
    public static ?Closure $headerActions = null;

    protected static ?string $slug = 'test';

    public array $log = [];

    protected function getHeaderActions(): array
    {
        return static::$headerActions ? (static::$headerActions)($this) : [];
    }
}
