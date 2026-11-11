<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('console output is never translated')
    ->expect('Blemli\SplitButton\Commands')
    ->not->toUse(['__', 'trans', 'trans_choice']);

arch('the package never depends on formsettings')
    ->expect('Blemli\SplitButton')
    ->not->toUse('Blemli\FormSettings');
