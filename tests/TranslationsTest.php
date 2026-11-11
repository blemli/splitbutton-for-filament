<?php

use Blemli\SplitButton\Enums\AutoPin;

it('ships the same keys, with the same placeholders, in every language', function (string $locale) {
    $en = require __DIR__ . '/../resources/lang/en/splitbutton.php';
    $other = require __DIR__ . "/../resources/lang/{$locale}/splitbutton.php";

    $placeholders = fn (array $lines): array => array_map(function (string $line): array {
        preg_match_all('/:[a-z]+/', $line, $matches);
        sort($matches[0]);

        return $matches[0];
    }, $lines);

    expect(array_keys($other))->toBe(array_keys($en))
        ->and($placeholders($other))->toBe($placeholders($en))
        ->and(array_filter($other, fn (string $line): bool => trim($line) === ''))->toBe([]);
})->with(['de', 'es', 'fr', 'it']);

it('ships no language the test above does not know', function () {
    $shipped = array_map(basename(...), glob(__DIR__ . '/../resources/lang/*', GLOB_ONLYDIR));
    sort($shipped);

    expect($shipped)->toBe(['de', 'en', 'es', 'fr', 'it']);
});

it('uses every key and no key that does not exist', function () {
    $keys = array_keys(require __DIR__ . '/../resources/lang/en/splitbutton.php');
    $sources = file_get_contents(__DIR__ . '/../resources/views/split-button.blade.php') . file_get_contents(__DIR__ . '/../src/SplitButton.php') . file_get_contents(__DIR__ . '/../src/Support/FlowBridge.php');

    preg_match_all("/(?:splitbutton\\.|\\\$lang \\. ')([a-z_]+)'/", $sources, $matches);

    // The strategy labels are looked up by the strategy's name.
    $used = array_values(array_unique([
        ...$matches[1],
        ...array_map(fn (AutoPin $strategy): string => "pin_{$strategy->value}", AutoPin::cases()),
    ]));
    sort($used);
    sort($keys);

    expect($used)->toBe($keys);
});
