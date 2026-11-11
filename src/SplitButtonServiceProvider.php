<?php

namespace Blemli\SplitButton;

use Blemli\SplitButton\Commands\InstallCommand;
use Blemli\SplitButton\Commands\UninstallCommand;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SplitButtonServiceProvider extends PackageServiceProvider
{
    public static string $name = 'splitbutton-for-filament';

    public const ASSET_PACKAGE = 'blemli/splitbutton-for-filament';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_splitbutton_layouts_table')
            ->hasCommands([InstallCommand::class, UninstallCommand::class]);
    }

    public function packageRegistered(): void
    {
        // Scoped, not singleton: the per-request layout cache must not
        // outlive the request on long-running workers.
        $this->app->scoped(SplitButtonManager::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            AlpineComponent::make('splitbutton', __DIR__ . '/../resources/dist/components/splitbutton.js'),
            Css::make('splitbutton', __DIR__ . '/../resources/dist/splitbutton.css'),
        ], static::ASSET_PACKAGE);

        $this->registerMacros();
    }

    /**
     * CreateAction::make()->split([...]) and ActionGroup::make([...])->split()
     * / ->overflow(2): the short way into a SplitButton.
     */
    protected function registerMacros(): void
    {
        Action::macro('split', function (array | Closure $options = []): SplitButton {
            /** @var Action $this */
            return SplitButton::make([$this])->options($options);
        });

        ActionGroup::macro('split', function (): SplitButton {
            /** @var ActionGroup $this */
            return SplitButton::make($this->getActions());
        });

        ActionGroup::macro('overflow', function (int | Closure | null $primaryCount = null): SplitButton {
            /** @var ActionGroup $this */
            return SplitButton::make($this->getActions())->overflow($primaryCount);
        });
    }
}
