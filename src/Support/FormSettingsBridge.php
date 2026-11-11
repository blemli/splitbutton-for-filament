<?php

namespace Blemli\SplitButton\Support;

use Blemli\SplitButton\SplitButton;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Throwable;

/**
 * The only place that knows about blemli/formsettings-for-filament — and
 * all it knows is one public method name. Neither package requires the
 * other: without formsettings (or with a version that predates quick
 * presets) this returns nothing.
 */
class FormSettingsBridge
{
    /**
     * The formsettings manager class. A property rather than an import so
     * this package never references formsettings code directly.
     */
    public static string $manager = 'Blemli\\FormSettings\\FormSettings';

    /**
     * @return array<Action>
     */
    public static function actions(SplitButton $button): array
    {
        if (! class_exists(static::$manager) || ! method_exists(static::$manager, 'quickActions')) {
            return [];
        }

        $resource = $button->getFormSettingsResource() ?? static::resourceOf($button);

        if ($resource === null) {
            return [];
        }

        try {
            $actions = app(static::$manager)->quickActions(
                $resource,
                $button->getFormSettingsOperation() ?? static::operationOf($button),
            );
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        return array_values(array_filter(
            is_array($actions) ? $actions : [],
            fn (mixed $action): bool => $action instanceof Action,
        ));
    }

    /**
     * @return class-string | null
     */
    protected static function resourceOf(SplitButton $button): ?string
    {
        try {
            $livewire = $button->getLivewire();

            if (($livewire === null) || ! method_exists($livewire, 'getResource')) {
                return null;
            }

            return $livewire::getResource();
        } catch (Throwable) {
            return null;
        }
    }

    protected static function operationOf(SplitButton $button): string
    {
        $first = $button->getFlatActions()[$button->getDeclaredName() ?? ''] ?? null;

        return match (true) {
            $first instanceof EditAction => 'edit',
            $first instanceof CreateAction => 'create',
            default => 'create',
        };
    }
}
