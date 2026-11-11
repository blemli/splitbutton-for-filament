<?php

namespace Blemli\SplitButton\Tests\Fixtures;

use Filament\Actions\Action;

/**
 * Stands in for Blemli\FormSettings\FormSettings: the bridge only ever
 * calls quickActions().
 */
class FakeFormSettings
{
    /** @var array<array{string, string}> */
    public static array $calls = [];

    /**
     * @return array<mixed>
     */
    public function quickActions(string $resource, string $operation = 'create'): array
    {
        static::$calls[] = [$resource, $operation];

        return [Action::make('fsq-quick-entry')->label('Quick entry'), 'not an action'];
    }
}
