<?php

it('ships the same keys in german and english', function () {
    $en = array_keys(require __DIR__ . '/../resources/lang/en/splitbutton.php');
    $de = array_keys(require __DIR__ . '/../resources/lang/de/splitbutton.php');

    expect($de)->toBe($en);
});

it('uses every key and no key that does not exist', function () {
    $keys = array_keys(require __DIR__ . '/../resources/lang/en/splitbutton.php');
    $sources = file_get_contents(__DIR__ . '/../resources/views/split-button.blade.php') . file_get_contents(__DIR__ . '/../src/SplitButton.php');

    preg_match_all("/(?:splitbutton\\.|\\\$lang \\. ')([a-z_]+)'/", $sources, $matches);

    $used = array_values(array_unique($matches[1]));
    sort($used);
    sort($keys);

    expect($used)->toBe($keys);
});
