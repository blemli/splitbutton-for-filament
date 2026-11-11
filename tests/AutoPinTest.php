<?php

use Blemli\SplitButton\Enums\AutoPin;
use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\SplitButtonManager;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Filament\Actions\Action;

/*
 * What sits on the button when it follows the user's picks: the last one
 * (recent), the one picked most (frequent), or the one picked most lately
 * (frecent) — and what a renamed action keeps of a user's layout.
 */

const DAY = 86400;

function pinButton(): SplitButton
{
    return SplitButton::make([
        Action::make('create'),
        Action::make('import'),
        Action::make('duplicate'),
        Action::make('export'),
    ])->editable()->livewire(new TestPage);
}

/**
 * An old habit (import, 10 picks two months ago), a new one (export, 3
 * picks this week) and one stray pick just now (duplicate).
 */
function picked(SplitButton $button, array $extra = []): void
{
    $layout = new Layout;
    $halfLife = 14.0 * DAY;
    $now = now()->getTimestamp();

    foreach (range(1, 10) as $_) {
        $layout->recordUse('import', $now - (60 * DAY), $halfLife);
    }

    foreach ([3, 2, 1] as $daysAgo) {
        $layout->recordUse('export', $now - ($daysAgo * DAY), $halfLife);
    }

    $layout->recordUse('duplicate', $now, $halfLife);

    app(SplitButtonManager::class)->save($button->getStateKey(), Layout::fromArray([...$layout->toArray(), ...$extra]));
}

it('understands every way of naming strategies', function () {
    expect(AutoPin::list(true))->toBe([AutoPin::Frecent])
        ->and(AutoPin::list(false))->toBe([])
        ->and(AutoPin::list(null))->toBe([])
        ->and(AutoPin::list('recent'))->toBe([AutoPin::Recent])
        ->and(AutoPin::list(['frequent', AutoPin::Recent, 'frequent', 'nonsense']))->toBe([AutoPin::Frequent, AutoPin::Recent])
        ->and(AutoPin::initial(true, [AutoPin::Frequent, AutoPin::Recent]))->toBe(AutoPin::Frequent)
        ->and(AutoPin::initial('recent', [AutoPin::Frequent, AutoPin::Recent]))->toBe(AutoPin::Recent)
        ->and(AutoPin::initial(AutoPin::Frecent, [AutoPin::Recent]))->toBeNull()
        ->and(AutoPin::initial(false, [AutoPin::Recent]))->toBeNull();
});

it('picks the last, the most and the lately most used entry', function () {
    $this->signIn();
    $this->travelTo('2026-06-01 12:00:00');

    picked(pinButton());

    expect(pinButton()->autoPin(AutoPin::Recent)->compose()['face'])->toBe('duplicate')
        ->and(pinButton()->autoPin(AutoPin::Frequent)->compose()['face'])->toBe('import')
        ->and(pinButton()->autoPin(AutoPin::Frecent)->compose()['face'])->toBe('export')
        ->and(pinButton()->autoPin()->compose()['face'])->toBe('export')
        ->and(pinButton()->autoPin('recent')->compose()['face'])->toBe('duplicate');
});

it('lets old picks fade as fast as the half-life says', function () {
    $this->signIn();
    $this->travelTo('2026-06-01 12:00:00');

    picked(pinButton());

    // With a half-life of a year, ten picks two months ago still outweigh three this week.
    $this->plugin()->frecencyHalfLife(365);
    expect(pinButton()->autoPin()->compose()['face'])->toBe('import');

    $this->plugin()->frecencyHalfLife(14);
    expect(pinButton()->autoPin()->compose()['face'])->toBe('export');

    // And the new habit fades too, once it is given up.
    $this->travel(200)->days();
    app(SplitButtonManager::class)->recordUse(pinButton()->getStateKey(), 'create');

    expect(pinButton()->autoPin()->compose()['face'])->toBe('create');
});

it('follows the picks for everybody with autoPin(), whatever a user once chose, and shows no switch', function () {
    $this->signIn();

    // The user had switched it off back when it was on offer.
    picked(pinButton(), ['auto_pin' => 'off']);

    $button = fn (): SplitButton => pinButton()->autoPin(AutoPin::Frequent);
    $state = $button()->getClientState();

    expect($button()->compose()['face'])->toBe('import')
        ->and($button()->isAutoPinOptional())->toBeFalse()
        ->and($state['canSwitchStrategy'])->toBeFalse()
        ->and($state['strategy'])->toBe('frequent')
        ->and($state['strategies'])->toBe([['name' => 'frequent', 'label' => 'Most used on the button', 'pick' => 'import']])
        ->and($state['track'])->toBeTrue();

    // Without edit mode it still counts picks and follows them.
    $static = fn (): SplitButton => pinButton()->editable(false)->autoPin(AutoPin::Recent);

    expect($static()->isStateful())->toBeTrue()
        ->and($static()->compose()['face'])->toBe('duplicate');

    // Panel-wide, with one button that is let off and one that leaves it to the user.
    $this->plugin()->autoPin(AutoPin::Recent);

    expect(pinButton()->compose()['face'])->toBe('duplicate')
        ->and(pinButton()->autoPin(false)->compose()['face'])->toBe('create')
        ->and(pinButton()->offerAutoPin(AutoPin::Frequent)->compose()['face'])->toBe('create');

    $this->plugin()->autoPin(false);
});

it('changes nothing with offerAutoPin() until the user switches a strategy on, unless it is on by default', function () {
    $this->signIn();

    picked(pinButton());

    expect(pinButton()->offerAutoPin()->compose()['face'])->toBe('create')
        ->and(pinButton()->offerAutoPin(byDefault: true)->compose()['face'])->toBe('export')
        ->and(pinButton()->offerAutoPin([AutoPin::Recent, AutoPin::Frequent], byDefault: true)->compose()['face'])->toBe('duplicate')
        ->and(pinButton()->offerAutoPin([AutoPin::Recent, AutoPin::Frequent], byDefault: AutoPin::Frequent)->compose()['face'])->toBe('import');

    // Off is off, whatever the default.
    picked(pinButton(), ['auto_pin' => 'off']);

    expect(pinButton()->offerAutoPin(byDefault: true)->compose()['face'])->toBe('create');
});

it('lets the user choose between the strategies on offer, and only those', function () {
    $this->signIn();

    $offered = fn (): SplitButton => pinButton()->offerAutoPin([AutoPin::Recent, AutoPin::Frequent, AutoPin::Frecent]);

    picked(pinButton(), ['auto_pin' => 'frequent']);
    $state = $offered()->getClientState();

    expect($offered()->compose()['face'])->toBe('import')
        ->and($state['strategy'])->toBe('frequent')
        ->and($state['canSwitchStrategy'])->toBeTrue()
        ->and($state['strategies'])->toBe([
            ['name' => 'recent', 'label' => 'Last used on the button', 'pick' => 'duplicate'],
            ['name' => 'frequent', 'label' => 'Most used on the button', 'pick' => 'import'],
            ['name' => 'frecent', 'label' => 'Most used lately on the button', 'pick' => 'export'],
        ]);

    // A strategy the developer no longer offers falls back to the default: none.
    expect(pinButton()->offerAutoPin(AutoPin::Recent)->compose()['face'])->toBe('create')
        ->and(pinButton()->offerAutoPin(false)->compose()['face'])->toBe('create');
});

it('takes only one strategy when the developer decides', function () {
    $this->signIn();

    picked(pinButton());

    // autoPin() is typed for one; a closure handing back several means the first.
    expect(pinButton()->autoPin(fn (): array => [AutoPin::Frequent, AutoPin::Recent])->getAutoPinStrategies())->toBe([AutoPin::Frequent]);
});

it('lets a pin set by hand win, and falls back to the first action when there is nothing to follow', function () {
    $this->signIn();

    // Nothing picked yet: the declared first action.
    expect(pinButton()->autoPin()->compose()['face'])->toBe('create');

    // Picked, and pinned by hand: the pin.
    picked(pinButton(), ['default' => 'create']);

    expect(pinButton()->autoPin(AutoPin::Frequent)->compose()['face'])->toBe('create')
        ->and(pinButton()->offerAutoPin(AutoPin::Frequent, byDefault: true)->compose()['face'])->toBe('create');

    // A hidden entry is never put on the button, however often it was picked.
    picked(pinButton(), ['hidden' => ['import']]);

    expect(pinButton()->autoPin(AutoPin::Frequent)->compose()['face'])->toBe('export');
});

it('breaks a tie for the entry picked later', function () {
    $layout = new Layout;
    $layout->recordUse('a', 1000, DAY);
    $layout->recordUse('b', 2000, DAY);

    expect($layout->pick(AutoPin::Frequent, ['a', 'b'], 3000, DAY))->toBe('b')
        ->and($layout->pick(AutoPin::Frequent, ['a'], 3000, DAY))->toBe('a')
        ->and($layout->pick(AutoPin::Frequent, ['c'], 3000, DAY))->toBeNull()
        ->and($layout->pick(AutoPin::Recent, ['a'], 3000, DAY))->toBeNull();
});

it('keeps the picks of at most a hundred entries, the faintest go first', function () {
    $layout = new Layout;

    foreach (range(1, Layout::MAX_ENTRIES + 5) as $index) {
        $layout->recordUse("entry-{$index}", 1000 + $index, DAY);
    }

    expect($layout->usage)->toHaveCount(Layout::MAX_ENTRIES)
        ->and($layout->usage)->not->toHaveKey('entry-1')
        ->and($layout->usage)->toHaveKey('entry-105');
});

it('reads only what it knows from a stored layout', function () {
    expect(Layout::fromArray(['auto_pin' => 'recent'])->autoPin)->toBe('recent')
        ->and(Layout::fromArray(['auto_pin' => 'off'])->autoPin)->toBe('off')
        ->and(Layout::fromArray(['auto_pin' => 'bogus'])->autoPin)->toBeNull()
        ->and(Layout::fromArray(['usage' => ['a' => ['count' => 0], 'b' => 'x', 'c' => ['count' => 2]]])->usage)
        ->toBe(['c' => ['count' => 2, 'score' => 2.0, 'at' => 0]]);
});

it('keeps a user\'s place, pin and picks when an action is renamed', function () {
    $this->signIn();

    $key = pinButton()->getStateKey();

    picked(pinButton(), ['order' => ['export', 'import', 'create', 'duplicate'], 'hidden' => ['duplicate'], 'default' => 'import', 'auto_pin' => 'frequent']);

    $renamed = fn (): SplitButton => SplitButton::make([
        Action::make('create'),
        Action::make('importShoots'),
        Action::make('copy'),
        Action::make('export'),
    ])->editable()->autoPin(AutoPin::Frequent)->renamed(['import' => 'importShoots', 'duplicate' => 'copy'])->livewire(new TestPage);

    $composition = $renamed()->compose();

    expect($composition['face'])->toBe('importShoots')
        ->and($composition['hasHidden'])->toBeTrue()
        ->and(array_column($composition['entries'][0], 'name'))->toBe(['export', 'importShoots', 'create', 'copy']);

    // Stored under the new names from then on.
    app(SplitButtonManager::class)->forgetCached();
    $stored = app(SplitButtonManager::class)->layout($key);

    expect($stored->order)->toBe(['export', 'importShoots', 'create', 'copy'])
        ->and($stored->hidden)->toBe(['copy'])
        ->and($stored->default)->toBe('importShoots')
        ->and($stored->usage)->toHaveKeys(['importShoots', 'copy', 'export'])
        ->and($stored->usage)->not->toHaveKey('import');
});

it('leaves a layout alone that holds none of the old names', function () {
    $layout = new Layout(order: ['a', 'b'], default: 'a');

    expect($layout->renamed(['x' => 'y']))->toBe($layout)
        ->and($layout->renamed([]))->toBe($layout)
        ->and($layout->renamed(['a' => 'c'])->order)->toBe(['c', 'b']);
});
