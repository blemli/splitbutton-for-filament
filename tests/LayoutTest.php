<?php

use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\SplitButtonManager;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

function editableButton(): SplitButton
{
    return SplitButton::make([
        Action::make('create'),
        Action::make('import'),
        Action::make('duplicate'),
        Action::make('export'),
    ])->editable()->rememberLastUsed()->livewire(new TestPage);
}

function store(SplitButton $button, array $layout): void
{
    app(SplitButtonManager::class)->save($button->getStateKey(), Layout::fromArray($layout));
}

function menu(SplitButton $button): array
{
    return array_map(fn (Action | ActionGroup $action): string => $action->getName(), $button->compose()['sections'][0] ?? []);
}

it('keys the state by panel, livewire component and first action', function () {
    $this->signIn();

    expect(editableButton()->getStateKey())
        ->toBe('admin::Blemli.SplitButton.Tests.Fixtures.TestPage::create')
        ->and(editableButton()->key('shoots')->getStateKey())
        ->toBe('admin::Blemli.SplitButton.Tests.Fixtures.TestPage::shoots');
});

it('is static without a user, without the feature, or when not authorized', function () {
    expect(editableButton()->isStateful())->toBeFalse();

    $this->signIn();

    expect(editableButton()->isStateful())->toBeTrue()
        ->and(SplitButton::make([Action::make('create')])->livewire(new TestPage)->isStateful())->toBeFalse();

    $this->plugin()->authorize(false);

    expect(editableButton()->isStateful())->toBeFalse();

    $this->plugin()->authorize(true);
});

it('takes the per-user features from the plugin unless the button says otherwise', function () {
    $this->signIn();

    $plain = fn (): SplitButton => SplitButton::make([Action::make('create'), Action::make('import')])->livewire(new TestPage);

    expect($plain()->isEditable())->toBeFalse();

    $this->plugin()->editable()->rememberLastUsed(byDefault: true)->editHint();

    expect($plain()->isEditable())->toBeTrue()
        ->and($plain()->hasEditHint())->toBeTrue()
        ->and($plain()->remembersLastUsed())->toBeTrue()
        ->and($plain()->remembersLastUsedByDefault())->toBeTrue()
        ->and($plain()->editable(false)->isEditable())->toBeFalse()
        ->and($plain()->rememberLastUsed(false)->remembersLastUsed())->toBeFalse();

    $this->plugin()->editable(false)->rememberLastUsed(false)->editHint(false);
});

it('applies the stored order, with new entries after it', function () {
    $this->signIn();

    store(editableButton(), ['order' => ['export', 'create', 'import']]);

    expect(menu(editableButton()))->toBe(['export', 'import', 'duplicate'])
        ->and(editableButton()->compose()['face'])->toBe('create');
});

it('leaves out the entries the user hid, and keeps the trigger so they can come back', function () {
    $this->signIn();

    store(editableButton(), ['hidden' => ['import', 'duplicate', 'export']]);

    $composition = editableButton()->compose();

    expect($composition['sections'])->toBe([])
        ->and($composition['hasTrigger'])->toBeTrue()
        ->and($composition['hasHidden'])->toBeTrue()
        ->and(collect($composition['entries'][0])->where('hidden', true)->pluck('name')->all())->toBe(['import', 'duplicate', 'export']);
});

it('puts the pinned entry on the button and the declared one back in the menu', function () {
    $this->signIn();

    store(editableButton(), ['default' => 'duplicate']);

    expect(editableButton()->compose()['face'])->toBe('duplicate')
        ->and(menu(editableButton()))->toBe(['create', 'import', 'export']);
});

it('falls back to the declared action when the pinned one is gone or hidden', function () {
    $this->signIn();

    store(editableButton(), ['default' => 'vanished']);

    expect(editableButton()->compose()['face'])->toBe('create');

    store(editableButton(), ['default' => 'import', 'hidden' => ['import']]);

    expect(editableButton()->compose()['face'])->toBe('create');
});

it('moves to the next action when the user hid the declared one', function () {
    $this->signIn();

    store(editableButton(), ['hidden' => ['create']]);

    expect(editableButton()->compose()['face'])->toBe('import');
});

it('puts the last used entry on the button only while the user has the toggle on', function () {
    $this->signIn();

    store(editableButton(), ['last_used' => 'export', 'default' => 'import']);

    expect(editableButton()->compose()['face'])->toBe('import');

    store(editableButton(), ['last_used' => 'export', 'default' => 'import', 'remember_last' => true]);

    expect(editableButton()->compose()['face'])->toBe('export');

    store(editableButton(), ['last_used' => 'export', 'remember_last' => false]);

    expect(editableButton()->rememberLastUsed(byDefault: true)->compose()['face'])->toBe('create');
});

it('starts with last used switched on when the developer says so', function () {
    $this->signIn();

    store(editableButton(), ['last_used' => 'export']);

    expect(editableButton()->compose()['face'])->toBe('create')
        ->and(editableButton()->rememberLastUsed(byDefault: true)->compose()['face'])->toBe('export');
});

it('ignores last used when the feature is off', function () {
    $this->signIn();

    store(editableButton(), ['last_used' => 'export', 'remember_last' => true]);

    expect(editableButton()->rememberLastUsed(false)->compose()['face'])->toBe('create');
});

it('pins to the front of an overflow row', function () {
    $this->signIn();

    store(editableButton(), ['default' => 'export']);

    $composition = editableButton()->overflow(2)->compose();

    expect(array_map(fn (Action $action): string => $action->getName(), $composition['faces']))->toBe(['export', 'create']);
});

it('hands the edit mode every entry, with labels and state', function () {
    $this->signIn();

    store(editableButton(), ['order' => ['export', 'create'], 'hidden' => ['import'], 'default' => 'export']);

    $state = editableButton()->getClientState();

    expect($state['key'])->toBe('admin::Blemli.SplitButton.Tests.Fixtures.TestPage::create')
        ->and($state['editable'])->toBeTrue()
        ->and($state['pinned'])->toBe('export')
        ->and($state['declared'])->toBe('create')
        ->and($state['canRememberLast'])->toBeTrue()
        ->and($state['rememberLast'])->toBeFalse()
        ->and($state['urls']['layout'])->toBe('/admin/splitbutton/layout')
        ->and($state['urls']['used'])->toBe('/admin/splitbutton/used')
        ->and($state['sections'][0])->toBe([
            ['name' => 'export', 'label' => 'Export', 'hidden' => false, 'pinnable' => true],
            ['name' => 'create', 'label' => 'Create', 'hidden' => false, 'pinnable' => true],
            ['name' => 'import', 'label' => 'Import', 'hidden' => true, 'pinnable' => true],
            ['name' => 'duplicate', 'label' => 'Duplicate', 'hidden' => false, 'pinnable' => true],
        ]);
});

it('sanitizes a layout read from storage or the request', function () {
    $layout = Layout::fromArray([
        'order' => ['a', 'a', '', 7, ['x'], str_repeat('x', 300), 'b'],
        'hidden' => 'nope',
        'default' => ['array'],
        'last_used' => 'b',
        'remember_last' => 1,
    ]);

    expect($layout->order)->toBe(['a', 'b'])
        ->and($layout->hidden)->toBe([])
        ->and($layout->default)->toBeNull()
        ->and($layout->lastUsed)->toBe('b')
        ->and($layout->rememberLast)->toBeTrue()
        ->and((new Layout)->isEmpty())->toBeTrue()
        ->and($layout->isEmpty())->toBeFalse();
});
