<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('installs non-interactively: config only, no migration', function () {
    $this->artisan('splitbutton-for-filament:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Register the plugin in your panel provider')
        ->assertSuccessful();

    expect(File::exists(config_path('splitbutton-for-filament.php')))->toBeTrue()
        ->and(File::glob(database_path('migrations/*_create_splitbutton_layouts_table.php')))->toBe([]);
});

it('publishes the migration when asked to, without running it', function () {
    $this->artisan('splitbutton-for-filament:install', ['--no-interaction' => true, '--migration' => true])
        ->expectsOutputToContain('Run it with: php artisan migrate')
        ->assertSuccessful();

    expect(File::glob(database_path('migrations/*_create_splitbutton_layouts_table.php')))->toHaveCount(1);
});

it('publishes and runs the migration with --run-migrations', function () {
    Schema::drop('splitbutton_layouts');

    $this->artisan('splitbutton-for-filament:install', ['--no-interaction' => true, '--run-migrations' => true])
        ->assertSuccessful();

    expect(Schema::hasTable('splitbutton_layouts'))->toBeTrue();
});

it('asks about the database store when run interactively', function () {
    $this->artisan('splitbutton-for-filament:install')
        ->expectsConfirmation('Store menus per user in the database (->persist())? This publishes a migration.', 'yes')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsOutputToContain('Run it with: php artisan migrate')
        ->assertSuccessful();

    expect(File::glob(database_path('migrations/*_create_splitbutton_layouts_table.php')))->toHaveCount(1);
});
