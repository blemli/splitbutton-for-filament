<?php

use Blemli\SplitButton\Enums\AutoPin;
use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\SplitButtonManager;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Blemli\SplitButton\Tests\Fixtures\User;
use Blemli\SplitButton\Tests\Fixtures\UsersTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
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
    TestPage::$headerActions = fn (): array => [shootButton()->editable()];

    Livewire::test(TestPage::class)
        ->assertSeeHtml('data-splitbutton=')
        ->assertSeeHtml('x-data="splitButton()"')
        ->assertSeeHtml('components/splitbutton.js')
        ->assertSeeHtml('fi-splitbutton-edit-row')
        ->assertSee('Customize')
        ->assertSee('Reset')
        ->assertDontSee('Last used on the button');
});

it('offers the last used switch only when the developer offered it', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [shootButton()->editable()->offerAutoPin(AutoPin::Recent)];

    Livewire::test(TestPage::class)
        ->assertSee('Last used on the button')
        ->assertSeeHtml('&quot;canSwitchStrategy&quot;:true');

    // Decided by the developer: followed, but nothing to switch.
    TestPage::$headerActions = fn (): array => [shootButton()->editable()->autoPin(AutoPin::Recent)];

    Livewire::test(TestPage::class)
        ->assertSeeHtml('&quot;canSwitchStrategy&quot;:false')
        ->assertSeeHtml('&quot;strategy&quot;:&quot;recent&quot;')
        ->assertSeeHtml('&quot;track&quot;:true');
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

it('enters edit mode through the menu entry and E, and through a long press only when asked to', function () {
    $this->signIn();

    // editable() alone: the visible entry, no long press.
    TestPage::$headerActions = fn (): array => [shootButton()->editable()];

    Livewire::test(TestPage::class)
        ->assertSeeHtml('fi-splitbutton-hint')
        ->assertSeeHtml('title="Press E to customize"')
        ->assertDontSeeHtml('fi-splitbutton-longpress')
        ->assertSeeHtml('&quot;longPress&quot;:false');

    TestPage::$headerActions = fn (): array => [shootButton()->editable()->editableWithLongPress()->editableWithMenuEntry(false)];

    Livewire::test(TestPage::class)
        ->assertDontSeeHtml('fi-splitbutton-hint')
        ->assertSeeHtml('title="Hold, or press E, to customize"')
        ->assertSeeHtml('fi-splitbutton-longpress')
        ->assertSeeHtml('&quot;longPress&quot;:true')
        ->assertSeeHtml('&quot;longPressDelay&quot;:500');

    // The delay is the panel's to set.
    $this->plugin()->longPressDelay(900);
    TestPage::$headerActions = fn (): array => [shootButton()->editable()->editableWithLongPress()];

    Livewire::test(TestPage::class)->assertSeeHtml('&quot;longPressDelay&quot;:900');

    // The legend names the modifier in the browser: ⌘ on a Mac, Ctrl elsewhere.
    Livewire::test(TestPage::class)
        ->assertSeeHtml('sb.keys(')
        ->assertSeeHtml(':mod')
        ->assertSeeHtml('Ctrl')
        ->assertDontSeeHtml('Ctrl ↑↓ move');

    $this->plugin()->longPressDelay(500);
});

it('leaves the keyboard shortcuts out when told to', function () {
    $this->signIn();

    TestPage::$headerActions = fn (): array => [shootButton()->editable()];

    Livewire::test(TestPage::class)
        ->assertSeeHtml('aria-keyshortcuts="E"')
        ->assertSeeHtml('fi-splitbutton-keys')
        ->assertSeeHtml('&quot;keyboard&quot;:true');

    TestPage::$headerActions = fn (): array => [shootButton()->editable()->editableWithKeyboard(false)];

    Livewire::test(TestPage::class)
        ->assertDontSeeHtml('aria-keyshortcuts')
        ->assertDontSeeHtml('fi-splitbutton-keys')
        ->assertDontSeeHtml('Press E to customize')
        ->assertSeeHtml('&quot;keyboard&quot;:false')
        ->assertSeeHtml('fi-splitbutton-hint');

    // Long press is then the only thing the tooltip can name.
    TestPage::$headerActions = fn (): array => [shootButton()->editable()->editableWithKeyboard(false)->editableWithLongPress()];

    Livewire::test(TestPage::class)->assertSeeHtml('title="Hold to customize"');

    // Panel-wide, with a button that keeps them.
    $this->plugin()->editable()->editableWithKeyboard(false);
    $plain = fn (): SplitButton => SplitButton::make([Action::make('a'), Action::make('b')])->livewire(new TestPage);

    expect($plain()->isEditableWithKeyboard())->toBeFalse()
        ->and($plain()->editableWithKeyboard()->isEditableWithKeyboard())->toBeTrue();

    $this->plugin()->editable(false)->editableWithKeyboard();
});

it('translates its own labels', function () {
    $this->signIn();
    app()->setLocale('de');
    TestPage::$headerActions = fn (): array => [shootButton()->editable()];

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
    TestPage::$headerActions = fn (): array => [shootButton()->chevronPosition('start')];

    Livewire::test(TestPage::class)->assertSeeHtml('fi-splitbutton-chevron-start');
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

it('runs a closure that asks for the record once per table row', function () {
    $this->signIn();
    $bob = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);

    $perTable = 0;
    $perRow = [];

    UsersTable::$recordActions = function () use (&$perTable, &$perRow): array {
        return [
            SplitButton::make([
                Action::make('greet')->label('Greet')->action(fn (User $record, UsersTable $livewire) => $livewire->log[] = "greet {$record->name}"),
            ])
                ->options(function () use (&$perTable): array {
                    $perTable++;

                    return [Action::make('shared')->label('Shared')->url('/shared')];
                })
                ->options(function (User $record) use (&$perRow): array {
                    $perRow[] = $record->name;

                    // Bob gets two links of his own, everybody else one.
                    return array_map(
                        fn (int $number): Action => Action::make("own-{$record->getKey()}-{$number}")
                            ->label("{$record->name} link {$number}")
                            ->url("/users/{$record->getKey()}/{$number}"),
                        range(1, $record->name === 'Bob' ? 2 : 1),
                    );
                }),
        ];
    };

    $test = Livewire::test(UsersTable::class);
    $html = $test->html();

    expect($perTable)->toBe(1)
        ->and(array_count_values($perRow))->toBe(['Ada' => 1, 'Bob' => 1])
        ->and(substr_count($html, 'Ada link 1'))->toBe(1)
        ->and(substr_count($html, 'Bob link 1'))->toBe(1)
        ->and(substr_count($html, 'Bob link 2'))->toBe(1)
        ->and($html)->not->toContain('Ada link 2')
        ->and($html)->toContain("/users/{$bob->getKey()}/2");

    // The entries declared up front still run, with the row they were clicked in.
    $test->callTableAction('greet', $bob)->assertSet('log', ['greet Bob']);
});

it('collapses to a plain button in the rows a per-row closure has nothing for', function () {
    $this->signIn();
    User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);

    UsersTable::$recordActions = fn (): array => [
        SplitButton::make([Action::make('open')->label('Open')->url('/open')])
            ->options(fn ($record): array => $record->name === 'Bob'
                ? [Action::make('extra')->label('Extra for Bob')->url('/extra')]
                : []),
    ];

    $html = Livewire::test(UsersTable::class)->html();

    expect(substr_count($html, 'Extra for Bob'))->toBe(1)
        ->and(substr_count($html, 'fi-splitbutton-split'))->toBe(1);
});

it('applies a user\'s layout to per-row entries by name', function () {
    $this->signIn();
    $this->plugin()->editable();

    UsersTable::$recordActions = fn (): array => [
        SplitButton::make([Action::make('open')->label('Open')->url('/open')])
            ->options(fn (User $record): array => [
                Action::make('profile')->label("Profile of {$record->name}")->url("/profile/{$record->getKey()}"),
                Action::make('orders')->label("Orders of {$record->name}")->url("/orders/{$record->getKey()}"),
            ]),
    ];

    $component = Livewire::test(UsersTable::class);
    $key = collect($component->instance()->getTable()->getRecordActions())->first()->getStateKey();

    app(SplitButtonManager::class)->save($key, new Layout(hidden: ['orders']));

    $html = Livewire::test(UsersTable::class)->html();

    expect($html)->toContain('Profile of Ada')
        ->and($html)->not->toContain('/orders/');
});

it('refuses a per-row entry that would have to be run by name', function () {
    $this->signIn();

    UsersTable::$recordActions = fn (): array => [
        SplitButton::make([Action::make('open')->label('Open')->url('/open')])
            ->options(fn (User $record): array => [
                Action::make("archive-{$record->getKey()}")->action(fn () => null),
            ]),
    ];

    expect(fn () => Livewire::test(UsersTable::class)->html())
        ->toThrow('comes from a per-row closure and is not a link');
});

it('still runs a record closure straight away outside a table', function () {
    $this->signIn();
    $calls = 0;

    TestPage::$headerActions = function () use (&$calls): array {
        return [
            SplitButton::make([Action::make('open')->label('Open')->url('/open')])
                ->options(function ($record) use (&$calls): array {
                    $calls++;

                    return [Action::make('runs')->label('Runs anywhere')->action(fn () => null)];
                }),
        ];
    };

    Livewire::test(TestPage::class)->assertSee('Runs anywhere');

    expect($calls)->toBeGreaterThan(0);
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

it('takes a dropdown width as an enum or as its string', function () {
    $actions = fn (): array => [Action::make('a'), Action::make('b')];

    expect(SplitButton::make($actions())->dropdownWidth('xs')->toHtml())->toContain('fi-width-xs')
        ->and(SplitButton::make($actions())->dropdownWidth(Width::Small)->toHtml())->toContain('fi-width-sm')
        ->and(SplitButton::make($actions())->dropdownWidth('my-own-class')->toHtml())->toContain('my-own-class')
        ->and(SplitButton::make($actions())->toHtml())->not->toContain('fi-width-');
});

it('opens the menu under the control, on the side its trigger is on', function () {
    $this->signIn();
    $actions = fn (): array => [Action::make('a')->label('Alpha'), Action::make('b')->label('Beta'), Action::make('c')->label('Gamma')];

    expect(SplitButton::make($actions())->getDropdownPlacement())->toBe('bottom-end')
        ->and(SplitButton::make($actions())->chevronPosition('start')->getDropdownPlacement())->toBe('bottom-start')
        ->and(SplitButton::make($actions())->chevronPosition('start')->dropdownPlacement('top-end')->getDropdownPlacement())->toBe('top-end')
        // a table may place the menu of a "More" trigger, never the chevron of a split button
        ->and(SplitButton::make($actions())->overflow(1)->defaultDropdownPlacement('bottom-start')->getDropdownPlacement())->toBe('bottom-start')
        ->and(SplitButton::make($actions())->defaultDropdownPlacement('bottom-start')->getDropdownPlacement())->toBe('bottom-end');

    // Filament gives page-header groups "bottom-end" when they have no placement of their own
    TestPage::$headerActions = fn (): array => [SplitButton::make($actions())->chevronPosition('start')];

    Livewire::test(TestPage::class)->assertSeeHtml('x-float.placement.bottom-start');
});

it('sizes and colours the whole control from the split button, unless the action has its own', function () {
    $html = SplitButton::make([Action::make('a')->label('Alpha'), Action::make('b')->label('Beta')])
        ->size('sm')
        ->color('danger')
        ->toHtml();

    // both halves: the button and its chevron
    expect(substr_count($html, 'fi-btn fi-size-sm'))->toBe(2)
        ->and(substr_count($html, 'fi-color-danger'))->toBeGreaterThanOrEqual(2);

    $own = SplitButton::make([Action::make('a')->label('Alpha')->size('lg')->color('success'), Action::make('b')->label('Beta')])
        ->size('sm')
        ->color('danger')
        ->toHtml();

    expect($own)->toContain('fi-size-lg')->toContain('fi-color-success');
});
