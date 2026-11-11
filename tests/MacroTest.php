<?php

use Blemli\SplitButton\SplitButton;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

it('splits a single action into a split button', function () {
    $button = Action::make('create')->split([Action::make('import')]);

    expect($button)->toBeInstanceOf(SplitButton::class)
        ->and(array_keys($button->getFlatActions()))->toBe(['create', 'import'])
        ->and($button->isOverflow())->toBeFalse();
});

it('turns an action group into a split button or an overflow row', function () {
    $actions = fn (): array => [Action::make('a'), Action::make('b'), Action::make('c')];

    $split = ActionGroup::make($actions())->split();
    $overflow = ActionGroup::make($actions())->overflow(2);

    expect($split)->toBeInstanceOf(SplitButton::class)
        ->and(array_keys($split->getFlatActions()))->toBe(['a', 'b', 'c'])
        ->and($overflow->isOverflow())->toBeTrue()
        ->and($overflow->getPrimaryCount())->toBe(2);
});
