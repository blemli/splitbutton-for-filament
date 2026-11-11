<?php

use Blemli\SplitButton\Enums\ChevronPosition;
use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

function names(array $actions): array
{
    return array_map(
        fn (Action | ActionGroup $action): string => $action instanceof Action ? $action->getName() : 'group',
        $actions,
    );
}

function attached(SplitButton $button): SplitButton
{
    return $button->livewire(new TestPage);
}

it('puts the first action on the button and the rest in the menu', function () {
    $composition = SplitButton::make([
        Action::make('create'),
        Action::make('import'),
        Action::make('duplicate'),
    ])->compose();

    expect(names($composition['faces']))->toBe(['create'])
        ->and($composition['sections'])->toHaveCount(1)
        ->and(names($composition['sections'][0]))->toBe(['import', 'duplicate'])
        ->and($composition['hasTrigger'])->toBeTrue()
        ->and($composition['face'])->toBe('create');
});

it('draws what is out front as buttons and the menu as items, whatever Filament asked the group before', function () {
    $button = SplitButton::make([
        Action::make('edit')->label('Edit it'),
        Action::make('archive')->label('Archive it'),
        Action::make('publish')->label('Publish it'),
        Action::make('delete')->label('Delete it'),
    ])->overflow(2);

    // Filament asks every group whether it is hidden before drawing it —
    // which resets all its children to "menu item".
    $button->compose();
    $button->isHidden();

    $html = $button->toHtml();

    preg_match_all('/class="(fi-ac-[a-z-]+)[^"]*"[^>]*>(?:\s|<[^>]+>)*([A-Z][a-z]+ it)/', $html, $matches, PREG_SET_ORDER);

    expect(array_column($matches, 1, 2))->toBe([
        'Edit it' => 'fi-ac-btn-action',
        'Archive it' => 'fi-ac-btn-action',
        'Publish it' => 'fi-ac-grouped-action',
        'Delete it' => 'fi-ac-grouped-action',
    ]);
});

it('respects an explicit style on the action out front', function () {
    $html = SplitButton::make([
        Action::make('edit')->label('Edit it')->link(),
        Action::make('archive')->label('Archive it'),
    ])->toHtml();

    expect($html)->toContain('fi-ac-link-action');
});

it('drops hidden and invisible actions before composing', function () {
    $composition = SplitButton::make([
        Action::make('create')->hidden(),
        Action::make('import'),
        Action::make('duplicate')->visible(false),
        Action::make('export'),
    ])->compose();

    expect(names($composition['faces']))->toBe(['import'])
        ->and(names($composition['sections'][0]))->toBe(['export']);
});

it('is just the button when nothing is left for the menu', function () {
    $button = SplitButton::make([
        Action::make('create')->label('Create shoot'),
        Action::make('import')->hidden(),
    ]);

    expect($button->compose()['hasTrigger'])->toBeFalse()
        ->and($button->toHtml())->toContain('Create shoot')
        ->and($button->toHtml())->not->toContain('fi-splitbutton');
});

it('keeps a single leftover in the menu of a split button', function () {
    $composition = SplitButton::make([
        Action::make('create'),
        Action::make('import'),
    ])->compose();

    expect(names($composition['sections'][0]))->toBe(['import']);
});

it('carries dropdown(false) groups into the menu as sections', function () {
    $composition = SplitButton::make([
        Action::make('submit'),
        ActionGroup::make([
            Action::make('discount'),
            Action::make('tax'),
        ])->dropdown(false),
        ActionGroup::make([
            Action::make('refresh'),
            Action::make('gone')->hidden(),
        ])->dropdown(false),
        ActionGroup::make([
            Action::make('void')->hidden(),
        ])->dropdown(false),
    ])->compose();

    expect(names($composition['faces']))->toBe(['submit'])
        ->and($composition['sections'])->toHaveCount(2)
        ->and(names($composition['sections'][0]))->toBe(['discount', 'tax'])
        ->and(names($composition['sections'][1]))->toBe(['refresh']);
});

it('never puts a submenu on the button', function () {
    $composition = SplitButton::make([
        ActionGroup::make([Action::make('nested')]),
        Action::make('create'),
    ])->compose();

    expect(names($composition['faces']))->toBe(['create'])
        ->and(names($composition['sections'][0]))->toBe(['group']);
});

it('adds array options to the menu', function () {
    $composition = SplitButton::make([Action::make('create')])
        ->options([Action::make('import'), Action::make('duplicate')])
        ->compose();

    expect(names($composition['sections'][0]))->toBe(['import', 'duplicate']);
});

it('resolves closure options once the button is attached, with the livewire injected', function () {
    $button = SplitButton::make([Action::make('create')])
        ->options(fn (TestPage $livewire): array => [
            Action::make('template-1')->label('Portrait'),
            Action::make('template-2')->label('Wedding'),
        ]);

    expect($button->getFlatActions())->toHaveKeys(['create'])->not->toHaveKey('template-1');

    attached($button);

    expect($button->getFlatActions())->toHaveKeys(['create', 'template-1', 'template-2'])
        ->and(names($button->compose()['sections'][0]))->toBe(['template-1', 'template-2']);
});

it('accepts a collection from the options closure and ignores anything that is no action', function () {
    $button = attached(
        SplitButton::make([Action::make('create')])
            ->options(fn () => collect([Action::make('one'), 'nope', null, Action::make('two')])),
    );

    expect(array_keys($button->getFlatActions()))->toBe(['create', 'one', 'two']);
});

it('never lets a closure option replace a declared action', function () {
    $button = attached(
        SplitButton::make([Action::make('create')->label('Declared')])
            ->options(fn (): array => [Action::make('create')->label('Impostor'), Action::make('extra')]),
    );

    expect($button->getFlatActions()['create']->getLabel())->toBe('Declared')
        ->and(array_keys($button->getFlatActions()))->toBe(['create', 'extra']);
});

it('keeps the primary count out front and tucks the rest under More', function () {
    $composition = SplitButton::make([
        Action::make('edit'),
        Action::make('archive'),
        Action::make('publish'),
        Action::make('delete'),
    ])->overflow(2)->compose();

    expect(names($composition['faces']))->toBe(['edit', 'archive'])
        ->and(names($composition['sections'][0]))->toBe(['publish', 'delete'])
        ->and($composition['hasTrigger'])->toBeTrue();
});

it('shows a single overflow leftover as a button of its own', function () {
    $composition = SplitButton::make([
        Action::make('edit'),
        Action::make('archive'),
    ])->overflow(1)->compose();

    expect(names($composition['faces']))->toBe(['edit', 'archive'])
        ->and($composition['hasTrigger'])->toBeFalse();
});

it('has no More button when nothing overflows', function () {
    $composition = SplitButton::make([
        Action::make('edit'),
        Action::make('archive'),
    ])->overflow(2)->compose();

    expect(names($composition['faces']))->toBe(['edit', 'archive'])
        ->and($composition['hasTrigger'])->toBeFalse();
});

it('overflows everything with a primary count of zero', function () {
    $composition = SplitButton::make([
        Action::make('edit'),
        Action::make('archive'),
    ])->overflow(0)->compose();

    expect($composition['faces'])->toBe([])
        ->and(names($composition['sections'][0]))->toBe(['edit', 'archive']);
});

it('takes the primary count from the config when none is given', function () {
    config()->set('splitbutton-for-filament.overflow.primary_count', 2);

    $composition = SplitButton::make([
        Action::make('a'), Action::make('b'), Action::make('c'), Action::make('d'),
    ])->overflow()->compose();

    expect(names($composition['faces']))->toBe(['a', 'b']);
});

it('rejects a negative primary count', function () {
    SplitButton::make([Action::make('edit')])->overflow(-1);
})->throws(InvalidArgumentException::class);

it('drops unauthorized actions only when asked to', function () {
    $actions = fn (): array => [
        Action::make('edit'),
        Action::make('delete')->authorize(false),
        Action::make('archive'),
    ];

    $filtered = SplitButton::make($actions())->filterUnauthorized()->compose();

    expect(names($filtered['sections'][0]))->toBe(['archive']);
});

it('resolves the chevron position from the fluent call, then the config', function () {
    expect(SplitButton::make([])->getChevronPosition())->toBe(ChevronPosition::End)
        ->and(SplitButton::make([])->chevronPosition('start')->getChevronPosition())->toBe(ChevronPosition::Start)
        ->and(SplitButton::make([])->chevronPosition(ChevronPosition::Start)->getChevronPosition())->toBe(ChevronPosition::Start);

    config()->set('splitbutton-for-filament.chevron_position', 'start');

    expect(SplitButton::make([])->getChevronPosition())->toBe(ChevronPosition::Start);
});
