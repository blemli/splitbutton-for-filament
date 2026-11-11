<?php

use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\SplitButtonManager;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Blemli\SplitButton\Tests\Fixtures\User;
use Blemli\SplitButton\Tests\Fixtures\UsersTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Livewire\Livewire;

function shootButton(): SplitButton
{
    return SplitButton::make([
        Action::make('create')->label('New shoot')->action(fn (TestPage $livewire) => $livewire->log[] = 'create'),
        Action::make('import')->label('Import shoots')->action(fn (TestPage $livewire) => $livewire->log[] = 'import'),
    ])->options(fn (): array => [
        Action::make('template-7')->label('Portrait')->action(fn (TestPage $livewire) => $livewire->log[] = 'template-7'),
    ]);
}

it('renders a static split button as plain Filament markup, without the script', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [shootButton()];

    Livewire::test(TestPage::class)
        ->assertSee('New shoot')
        ->assertSee('Import shoots')
        ->assertSee('Portrait')
        ->assertSeeHtml('fi-splitbutton-split')
        ->assertSeeHtml('x-data="filamentDropdown"')
        ->assertSeeHtml('fi-dropdown-trigger')
        ->assertDontSeeHtml('data-splitbutton=')
        ->assertDontSeeHtml('x-load-src');
});

it('runs the button, the menu entries and the closure entries through Filament', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [shootButton()];

    Livewire::test(TestPage::class)
        ->callAction('create')
        ->callAction('import')
        ->callAction('template-7')
        ->assertSet('log', ['create', 'import', 'template-7']);
});

it('loads the edit mode only for editable buttons', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [shootButton()->editable()->editHint()];

    Livewire::test(TestPage::class)
        ->assertSeeHtml('data-splitbutton=')
        ->assertSeeHtml('x-data="splitButton()"')
        ->assertSeeHtml('components/splitbutton.js')
        ->assertSeeHtml('fi-splitbutton-edit-row')
        ->assertSee('Customize')
        ->assertSee('Reset')
        ->assertDontSee('Last used on the button');
});

it('offers the last used toggle only when the developer opted in', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [shootButton()->editable()->rememberLastUsed()];

    Livewire::test(TestPage::class)->assertSee('Last used on the button');
});

it('stays static for guests even when editable', function () {
    TestPage::$headerActions = fn (): array => [shootButton()->editable()];

    Livewire::test(TestPage::class)
        ->assertSee('New shoot')
        ->assertDontSeeHtml('data-splitbutton=');
});

it('renders the pinned entry as the button', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [shootButton()->editable()];

    $key = 'admin::Blemli.SplitButton.Tests.Fixtures.TestPage::create';
    app(SplitButtonManager::class)->save($key, new Layout(default: 'template-7', hidden: ['import']));

    // The state attribute lists every label for edit mode — look at what
    // is actually drawn.
    $html = preg_replace('/data-splitbutton="[^"]*"/', '', Livewire::test(TestPage::class)->html());

    $face = strpos($html, 'Portrait');
    $trigger = strpos($html, 'fi-dropdown-trigger');
    $panel = strpos($html, 'fi-dropdown-panel');

    expect($face)->toBeLessThan($trigger)
        ->and(strpos($html, 'New shoot'))->toBeGreaterThan($panel)
        ->and($html)->not->toContain('Import shoots');
});

it('translates its own labels', function () {
    $this->signIn();
    app()->setLocale('de');
    TestPage::$headerActions = fn (): array => [shootButton()->editable()->editHint()];

    Livewire::test(TestPage::class)
        ->assertSee('Anpassen')
        ->assertSee('Zurücksetzen')
        ->assertSee('Weitere Optionen');
});

it('labels the More trigger, translated, and takes a label of its own', function () {
    $actions = fn (): array => [Action::make('a'), Action::make('b'), Action::make('c')];

    expect(SplitButton::make($actions())->overflow()->getLabel())->toBe('More')
        ->and(SplitButton::make($actions())->overflow()->label('Manage')->getLabel())->toBe('Manage');

    config()->set('splitbutton-for-filament.overflow.label', 'Manage');

    expect(SplitButton::make($actions())->overflow()->getLabel())->toBe('Manage');

    config()->set('splitbutton-for-filament.overflow.label', null);
    app()->setLocale('de');

    expect(SplitButton::make($actions())->overflow()->getLabel())->toBe('Mehr');
});

it('renders an overflow row with its More menu', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [
        SplitButton::make([
            Action::make('edit')->label('Edit it'),
            Action::make('archive')->label('Archive it'),
            Action::make('delete')->label('Delete it'),
        ])->overflow(),
    ];

    Livewire::test(TestPage::class)
        ->assertSeeHtml('fi-splitbutton-overflow')
        ->assertSeeInOrder(['Edit it', 'More', 'Archive it', 'Delete it']);
});

it('puts the trigger first when asked to', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [shootButton()->triggerPosition('start')];

    Livewire::test(TestPage::class)->assertSeeHtml('fi-splitbutton-trigger-start');
});

it('works as a table row action, per record', function () {
    $this->signIn();
    $bob = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);

    UsersTable::$recordActions = fn (): array => [
        SplitButton::make([
            Action::make('greet')->label('Greet')->action(fn (User $record, UsersTable $livewire) => $livewire->log[] = "greet {$record->name}"),
            Action::make('promote')->label('Promote')->action(fn (User $record, UsersTable $livewire) => $livewire->log[] = "promote {$record->name}"),
            Action::make('bobOnly')->label('Only for Bob')->visible(fn (?User $record): bool => $record?->name === 'Bob'),
        ])->options(fn (): array => [
            Action::make('dynamic')->label('Dynamic')->action(fn (User $record, UsersTable $livewire) => $livewire->log[] = "dynamic {$record->name}"),
        ]),
    ];

    Livewire::test(UsersTable::class)
        ->assertSee('Greet')
        ->assertSee('Promote')
        ->assertSee('Dynamic')
        ->assertSeeHtml('fi-splitbutton-split')
        ->callTableAction('greet', $bob)
        ->callTableAction('promote', $bob)
        ->callTableAction('dynamic', $bob)
        ->assertSet('log', ['greet Bob', 'promote Bob', 'dynamic Bob']);

    // "Only for Bob" is drawn once: in Bob's row, not in Ada's.
    expect(substr_count(Livewire::test(UsersTable::class)->html(), 'Only for Bob'))->toBe(1);
});

it('keeps Filament\'s own group behaviour: hidden when every entry is hidden', function () {
    $button = SplitButton::make([
        Action::make('a')->hidden(),
        Action::make('b')->hidden(),
    ]);

    expect($button->isHidden())->toBeTrue()
        ->and(SplitButton::make([Action::make('a')])->isHidden())->toBeFalse()
        ->and(SplitButton::make([Action::make('a')]))->toBeInstanceOf(ActionGroup::class);
});

it('takes the colour, size and outline of the button for its chevron', function () {
    $button = SplitButton::make([
        Action::make('create')->color('danger')->outlined()->size('lg'),
        Action::make('import'),
    ]);
    $button->compose();

    expect($button->getColor())->toBe('danger')
        ->and($button->isOutlined())->toBeTrue()
        ->and($button->getSize())->toBe('lg')
        ->and($button->isLabelHidden())->toBeTrue()
        ->and($button->getLabel())->toBe('More options');

    $own = SplitButton::make([Action::make('create')->color('danger'), Action::make('import')])->color('gray');
    $own->compose();

    expect($own->getColor())->toBe('gray');
});
