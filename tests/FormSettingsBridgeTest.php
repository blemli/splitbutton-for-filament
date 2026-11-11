<?php

use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\Support\FormSettingsBridge;
use Blemli\SplitButton\Tests\Fixtures\FakeFormSettings;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;

afterEach(fn () => FormSettingsBridge::$manager = 'Blemli\\FormSettings\\FormSettings');

it('adds nothing while formsettings-for-filament is not installed', function () {
    FormSettingsBridge::$manager = 'Blemli\\FormSettings\\FormSettings';

    expect(class_exists(FormSettingsBridge::$manager))->toBeFalse();

    $button = SplitButton::make([Action::make('create')])
        ->formSettingsPresets(resource: 'App\\Filament\\Resources\\ShootResource')
        ->livewire(new TestPage);

    expect(array_keys($button->getFlatActions()))->toBe(['create']);
});

it('lists the quick presets formsettings hands over, as ordinary entries', function () {
    FormSettingsBridge::$manager = FakeFormSettings::class;
    FakeFormSettings::$calls = [];

    $button = SplitButton::make([CreateAction::make()])
        ->formSettingsPresets(resource: 'App\\Filament\\Resources\\ShootResource')
        ->livewire(new TestPage);

    expect(array_keys($button->getFlatActions()))->toBe(['create', 'fsq-quick-entry'])
        ->and(FakeFormSettings::$calls)->toBe([['App\\Filament\\Resources\\ShootResource', 'create']]);

    FakeFormSettings::$calls = [];

    SplitButton::make([EditAction::make()])
        ->formSettingsPresets(resource: 'App\\Filament\\Resources\\ShootResource')
        ->livewire(new TestPage)
        ->getFlatActions();

    SplitButton::make([Action::make('anything')])
        ->formSettingsPresets(resource: 'App\\Filament\\Resources\\ShootResource', operation: 'edit')
        ->livewire(new TestPage)
        ->getFlatActions();

    expect(FakeFormSettings::$calls)->toBe([
        ['App\\Filament\\Resources\\ShootResource', 'edit'],
        ['App\\Filament\\Resources\\ShootResource', 'edit'],
    ]);

    // No resource to be found on a plain page: nothing to ask for.
    FakeFormSettings::$calls = [];

    SplitButton::make([CreateAction::make()])->formSettingsPresets()->livewire(new TestPage)->getFlatActions();

    expect(FakeFormSettings::$calls)->toBe([]);
});
