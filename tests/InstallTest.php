<?php

use Blemli\SplitButton\SplitButtonPlugin;
use Blemli\SplitButton\Support\PanelRegistrar;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('installs non-interactively: config only, no migration', function () {
    $this->artisan('splitbutton:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Register the plugin in your panel provider')
        ->assertSuccessful();

    expect(File::exists(config_path('splitbutton-for-filament.php')))->toBeTrue()
        ->and(File::glob(database_path('migrations/*_create_splitbutton_layouts_table.php')))->toBe([]);
});

it('answers to the package name as well', function () {
    $this->artisan('splitbutton-for-filament:install', ['--no-interaction' => true])->assertSuccessful();
    $this->artisan('splitbutton-for-filament:uninstall', ['--force' => true])->assertSuccessful();

    expect(File::exists(config_path('splitbutton-for-filament.php')))->toBeFalse();
});

it('publishes the migration when asked to, without running it', function () {
    $this->artisan('splitbutton:install', ['--no-interaction' => true, '--migration' => true])
        ->expectsOutputToContain('Run it with: php artisan migrate')
        ->assertSuccessful();

    expect(File::glob(database_path('migrations/*_create_splitbutton_layouts_table.php')))->toHaveCount(1);
});

it('publishes and runs the migration with --run-migrations', function () {
    Schema::drop('splitbutton_layouts');

    $this->artisan('splitbutton:install', ['--no-interaction' => true, '--run-migrations' => true])
        ->assertSuccessful();

    expect(Schema::hasTable('splitbutton_layouts'))->toBeTrue();
});

it('asks about the database store when run interactively', function () {
    $this->artisan('splitbutton:install')
        ->expectsConfirmation('Store menus per user in the database (->persist())? This publishes a migration.', 'yes')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsOutputToContain('Run it with: php artisan migrate')
        ->expectsOutputToContain('->plugins([SplitButtonPlugin::make()->persist()])')
        ->assertSuccessful();

    expect(File::glob(database_path('migrations/*_create_splitbutton_layouts_table.php')))->toHaveCount(1);
});

/*
 * Registering the plugin in a panel provider.
 */

function panelProvider(string $name = 'Admin', string $id = 'admin', ?string $body = null): string
{
    $body ??= <<<'PHP'
                ->default()
                ->id('__ID__')
                ->plugins([
                    FirstPlugin::make(),
                    SecondPlugin::make() // no comma yet
                ])
                ->middleware([]);
    PHP;

    $code = "<?php\n\nnamespace App\\Providers\\Filament;\n\nuse Filament\\Panel;\nuse Filament\\PanelProvider;\n\n"
        . "class {$name}PanelProvider extends PanelProvider\n{\n    public function panel(Panel \$panel): Panel\n    {\n        return \$panel\n"
        . str_replace('__ID__', $id, $body) . "\n    }\n}\n";

    File::ensureDirectoryExists(app_path('Providers/Filament'));
    File::put($path = app_path("Providers/Filament/{$name}PanelProvider.php"), $code);

    return $path;
}

it('registers the plugin in the provider of the panel named with --panel', function () {
    $path = panelProvider();
    panelProvider('Customer', 'customer');

    $this->artisan('splitbutton:install', ['--no-interaction' => true, '--panel' => 'admin'])
        ->expectsOutputToContain('Registered SplitButtonPlugin::make() in app/Providers/Filament/AdminPanelProvider.php.')
        ->assertSuccessful();

    // The fixture's line endings are the checkout's: CRLF on Windows.
    $code = str_replace("\r\n", "\n", File::get($path));

    expect($code)->toContain("use Filament\\PanelProvider;\nuse Blemli\\SplitButton\\SplitButtonPlugin;\n")
        ->and($code)->toContain("                SecondPlugin::make(), // no comma yet\n                SplitButtonPlugin::make(),\n            ])")
        ->and(File::get(app_path('Providers/Filament/CustomerPanelProvider.php')))->not->toContain('SplitButtonPlugin');

    token_get_all($code, TOKEN_PARSE);
});

it('registers it with ->persist() when the menus go into the database', function () {
    $path = panelProvider();

    $this->artisan('splitbutton:install', ['--no-interaction' => true, '--panel' => 'admin', '--migration' => true])
        ->assertSuccessful();

    expect(File::get($path))->toContain('SplitButtonPlugin::make()->persist(),');
});

it('touches no file without --panel when nobody can be asked', function () {
    $path = panelProvider();
    $before = File::get($path);

    $this->artisan('splitbutton:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Register the plugin in your panel provider: ->plugins([SplitButtonPlugin::make()])')
        ->assertSuccessful();

    expect(File::get($path))->toBe($before);
});

it('asks before it registers, and takes no for an answer', function () {
    $path = panelProvider();
    $before = File::get($path);

    $this->artisan('splitbutton:install')
        ->expectsConfirmation('Store menus per user in the database (->persist())? This publishes a migration.', 'no')
        ->expectsConfirmation('Register the plugin in app/Providers/Filament/AdminPanelProvider.php?', 'no')
        ->expectsOutputToContain('Register the plugin in your panel provider')
        ->assertSuccessful();

    expect(File::get($path))->toBe($before);

    $this->artisan('splitbutton:install')
        ->expectsConfirmation('Store menus per user in the database (->persist())? This publishes a migration.', 'no')
        ->expectsConfirmation('Register the plugin in app/Providers/Filament/AdminPanelProvider.php?', 'yes')
        ->assertSuccessful();

    expect(File::get($path))->toContain('SplitButtonPlugin::make(),');
});

it('lets the developer choose between several panel providers', function () {
    panelProvider();
    $customer = panelProvider('Customer', 'customer');

    $this->artisan('splitbutton:install')
        ->expectsConfirmation('Store menus per user in the database (->persist())? This publishes a migration.', 'no')
        ->expectsChoice('Register the plugin in which panel provider?', 'app/Providers/Filament/CustomerPanelProvider.php', [
            'app/Providers/Filament/AdminPanelProvider.php',
            'app/Providers/Filament/CustomerPanelProvider.php',
            'None, I will do it myself',
        ])
        ->assertSuccessful();

    expect(File::get($customer))->toContain('SplitButtonPlugin::make(),')
        ->and(File::get(app_path('Providers/Filament/AdminPanelProvider.php')))->not->toContain('SplitButtonPlugin');
});

it('leaves a provider alone that registers the plugin already, or that it cannot read', function () {
    $path = panelProvider(body: "            ->id('admin')\n            ->plugins([SplitButtonPlugin::make()]);");
    $before = File::get($path);

    $this->artisan('splitbutton:install', ['--no-interaction' => true, '--panel' => 'admin', '--migration' => true])
        ->expectsOutputToContain('The plugin is already registered in app/Providers/Filament/AdminPanelProvider.php.')
        ->expectsOutputToContain('Add ->persist() to it')
        ->assertSuccessful();

    expect(File::get($path))->toBe($before);

    $this->artisan('splitbutton:install', ['--no-interaction' => true, '--panel' => 'nope'])
        ->expectsOutputToContain('No panel provider with the id [nope] found')
        ->assertSuccessful();
});

it('writes a plugins list into a provider that has none yet', function () {
    // The chain ends behind the closure, not at the semicolon inside it.
    $fresh = "<?php\n\nnamespace App\\Providers\\Filament;\n\nuse Filament\\Panel;\n\nclass P extends PanelProvider\n{\n    public function panel(Panel \$panel): Panel\n    {\n        return \$panel\n            ->default()\n            ->bootUsing(function () { foo(); })\n            ->id('admin');\n    }\n}\n";

    expect(PanelRegistrar::register($fresh, SplitButtonPlugin::class, 'SplitButtonPlugin::make()'))
        ->toContain("            ->id('admin')\n            ->plugins([\n                SplitButtonPlugin::make(),\n            ]);\n    }")
        ->toContain("use Filament\\Panel;\nuse Blemli\\SplitButton\\SplitButtonPlugin;");
});

it('fits the entry to the list it finds', function (string $list, string $expected) {
    $code = "<?php\n\nnamespace App;\n\nclass P\n{\n    public function panel(\$panel)\n    {\n        return \$panel\n            ->plugins({$list});\n    }\n}\n";

    $result = PanelRegistrar::register($code, SplitButtonPlugin::class, 'SplitButtonPlugin::make()');

    expect($result)->toContain("->plugins({$expected});")
        ->and($result)->toContain("namespace App;\n\nuse Blemli\\SplitButton\\SplitButtonPlugin;\n\nclass P");
})->with([
    'empty' => ['[]', '[SplitButtonPlugin::make()]'],
    'one line' => ['[A::make(), B::make()]', '[A::make(), B::make(), SplitButtonPlugin::make()]'],
    'trailing comma' => ["[\n                A::make(),\n            ]", "[\n                A::make(),\n                SplitButtonPlugin::make(),\n            ]"],
    'nested arrays' => ["[\n                A::make(['x' => [1, 2]])\n            ]", "[\n                A::make(['x' => [1, 2]]),\n                SplitButtonPlugin::make(),\n            ]"],
]);

it('writes the line endings the file already has', function () {
    $windows = "<?php\r\n\r\nnamespace App;\r\n\r\nuse Filament\\Panel;\r\n\r\nclass P\r\n{\r\n    public function panel(\$panel)\r\n    {\r\n        return \$panel\r\n            ->plugins([\r\n                A::make(),\r\n            ]);\r\n    }\r\n}\r\n";

    $result = PanelRegistrar::register($windows, SplitButtonPlugin::class, 'SplitButtonPlugin::make()');

    expect($result)->toContain("A::make(),\r\n                SplitButtonPlugin::make(),\r\n            ]);")
        ->and($result)->toContain("use Filament\\Panel;\r\nuse Blemli\\SplitButton\\SplitButtonPlugin;\r\n")
        ->and(preg_match('/(?<!\r)\n/', $result))->toBe(0);

    // A new list in a file that has none.
    $fresh = str_replace("            ->plugins([\r\n                A::make(),\r\n            ]);", "            ->id('admin');", $windows);

    expect(preg_match('/(?<!\r)\n/', PanelRegistrar::register($fresh, SplitButtonPlugin::class, 'SplitButtonPlugin::make()')))->toBe(0);
});

it('gives up rather than guess', function () {
    expect(PanelRegistrar::register('<?php this is not php', SplitButtonPlugin::class, 'SplitButtonPlugin::make()'))->toBeNull()
        ->and(PanelRegistrar::register("<?php\n\nnamespace App;\n\nclass P\n{\n    public function panel(\$p)\n    {\n        return \$p->id('x');\n    }\n}\n", SplitButtonPlugin::class, 'SplitButtonPlugin::make()'))->toBeNull()
        ->and(PanelRegistrar::register("<?php\n\nreturn \$panel\n    ->plugins([]);\n", SplitButtonPlugin::class, 'SplitButtonPlugin::make()'))
        ->toContain('->plugins([\\Blemli\\SplitButton\\SplitButtonPlugin::make()]);');
});
