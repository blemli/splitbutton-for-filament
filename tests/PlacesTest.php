<?php

use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\Tests\Fixtures\SchemaHost;
use Blemli\SplitButton\Tests\Fixtures\SplitHost;
use Blemli\SplitButton\Tests\Fixtures\User;
use Blemli\SplitButton\Tests\Fixtures\UsersTable;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Livewire\Livewire;

/*
 * Every place Filament takes an action group: the button draws, its menu
 * entries (also the ones from a closure) run, and the per-user layer loads.
 */

function placeButton(string $prefix, string $host): SplitButton
{
    return SplitButton::make([
        Action::make("{$prefix}Main")->label("{$prefix} main")->action(fn ($livewire) => $livewire->log[] = "{$prefix}Main"),
        Action::make("{$prefix}Second")->label("{$prefix} second")->action(fn ($livewire) => $livewire->log[] = "{$prefix}Second"),
    ])
        ->options(fn (): array => [
            Action::make("{$prefix}Dynamic")->label("{$prefix} dynamic")->action(fn ($livewire) => $livewire->log[] = "{$prefix}Dynamic"),
        ])
        ->editable();
}

function assertDrawn($test, string $prefix): void
{
    $test
        ->assertSee("{$prefix} main")
        ->assertSee("{$prefix} second")
        ->assertSee("{$prefix} dynamic")
        ->assertSeeHtml('fi-splitbutton-split')
        ->assertSeeHtml('data-splitbutton=');
}

it('works as a table header action', function () {
    $this->signIn();
    UsersTable::$headerActions = fn (): array => [placeButton('header', UsersTable::class)];

    $test = Livewire::test(UsersTable::class);
    assertDrawn($test, 'header');

    $test->callAction(TestAction::make('headerMain')->table())
        ->callAction(TestAction::make('headerSecond')->table())
        ->callAction(TestAction::make('headerDynamic')->table())
        ->assertSet('log', ['headerMain', 'headerSecond', 'headerDynamic']);
});

it('works as a table toolbar action', function () {
    $this->signIn();
    UsersTable::$toolbarActions = fn (): array => [placeButton('toolbar', UsersTable::class)];

    $test = Livewire::test(UsersTable::class);
    assertDrawn($test, 'toolbar');

    $test->callAction(TestAction::make('toolbarSecond')->table())
        ->callAction(TestAction::make('toolbarDynamic')->table())
        ->assertSet('log', ['toolbarSecond', 'toolbarDynamic']);
});

it('works as a table empty-state action', function () {
    $this->signIn();
    UsersTable::$empty = true;
    UsersTable::$emptyStateActions = fn (): array => [placeButton('empty', UsersTable::class)];

    $test = Livewire::test(UsersTable::class);
    assertDrawn($test, 'empty');

    $test->callAction(TestAction::make('emptySecond')->table())
        ->callAction(TestAction::make('emptyDynamic')->table())
        ->assertSet('log', ['emptySecond', 'emptyDynamic']);
});

it('works in an Actions block of a form, which is also how pages draw their form actions', function () {
    $this->signIn();
    SchemaHost::$form = fn (): array => [
        TextInput::make('name'),
        Actions::make([placeButton('form', SchemaHost::class)])->key('buttons'),
    ];

    $test = Livewire::test(SchemaHost::class);
    assertDrawn($test, 'form');

    $test->callAction(TestAction::make('formMain')->schemaComponent('buttons', schema: 'form'))
        ->callAction(TestAction::make('formSecond')->schemaComponent('buttons', schema: 'form'))
        ->callAction(TestAction::make('formDynamic')->schemaComponent('buttons', schema: 'form'))
        ->assertSet('log', ['formMain', 'formSecond', 'formDynamic']);
});

it('works in an Actions block of an infolist', function () {
    $this->signIn();
    SchemaHost::$infolist = fn (): array => [
        TextEntry::make('name'),
        Actions::make([placeButton('info', SchemaHost::class)])->key('buttons'),
    ];

    $test = Livewire::test(SchemaHost::class);
    assertDrawn($test, 'info');

    $test->callAction(TestAction::make('infoSecond')->schemaComponent('buttons', schema: 'infolist'))
        ->callAction(TestAction::make('infoDynamic')->schemaComponent('buttons', schema: 'infolist'))
        ->assertSet('log', ['infoSecond', 'infoDynamic']);
});

it('works as a section header action', function () {
    $this->signIn();
    SchemaHost::$form = fn (): array => [
        Section::make('Details')
            ->key('details')
            ->headerActions([placeButton('section', SchemaHost::class)])
            ->schema([TextInput::make('name')]),
    ];

    $test = Livewire::test(SchemaHost::class);
    assertDrawn($test, 'section');

    $test->callAction(TestAction::make('sectionSecond')->schemaComponent('details', schema: 'form'))
        ->callAction(TestAction::make('sectionDynamic')->schemaComponent('details', schema: 'form'))
        ->assertSet('log', ['sectionSecond', 'sectionDynamic']);
});

it('works in a modal footer', function () {
    $this->signIn();
    SchemaHost::$open = fn (): Action => Action::make('open')
        ->requiresConfirmation()
        ->extraModalFooterActions([placeButton('modal', SchemaHost::class)])
        ->action(fn () => null);

    $test = Livewire::test(SchemaHost::class)->mountAction('open');

    // Livewire 4 sends an opened modal as a partial; Livewire 3 re-renders the component.
    $modal = ($test->effects['partials']['action-modals'] ?? '') . $test->html();

    expect($modal)
        ->toContain('modal main')
        ->toContain('modal second')
        ->toContain('modal dynamic')
        ->toContain('fi-splitbutton-split')
        ->toContain('data-splitbutton=');

    Livewire::test(SchemaHost::class)
        ->callAction([TestAction::make('open'), TestAction::make('modalSecond')])
        ->assertSet('log', ['modalSecond']);

    Livewire::test(SchemaHost::class)
        ->callAction([TestAction::make('open'), TestAction::make('modalDynamic')])
        ->assertSet('log', ['modalDynamic']);
});

it('works on a custom Livewire component through the trait', function () {
    $this->signIn();
    SplitHost::$buttons = fn (): array => ['orders' => placeButton('own', SplitHost::class)];

    $test = Livewire::test(SplitHost::class);
    assertDrawn($test, 'own');

    expect($test->instance()->getSplitButton('orders'))->toBeInstanceOf(SplitButton::class)
        ->and($test->instance()->getSplitButton('nope'))->toBeNull()
        ->and($test->instance()->getSplitButton('orders')->getStateKey())->toBe('admin::Blemli.SplitButton.Tests.Fixtures.SplitHost::ownMain');

    $test->callAction('ownMain')
        ->callAction('ownSecond')
        ->callAction('ownDynamic')
        ->assertSet('log', ['ownMain', 'ownSecond', 'ownDynamic']);
});

it('keeps one layout per button in each of these places', function () {
    $this->signIn();
    UsersTable::$headerActions = fn (): array => [placeButton('header', UsersTable::class)];
    SchemaHost::$form = fn (): array => [Actions::make([placeButton('form', SchemaHost::class)])->key('buttons')];

    $table = Livewire::test(UsersTable::class)->instance();
    $header = $table->getTable()->getHeaderActions()[0];

    expect($header->getStateKey())->toBe('admin::Blemli.SplitButton.Tests.Fixtures.UsersTable::headerMain')
        ->and($header->isStateful())->toBeTrue();

    User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);

    expect(Livewire::test(SchemaHost::class)->html())
        ->toContain('admin::Blemli.SplitButton.Tests.Fixtures.SchemaHost::formMain');
});
