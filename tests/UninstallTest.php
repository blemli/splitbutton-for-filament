<?php

use Blemli\SplitButton\Commands\UninstallCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

function fakePublishedPaths(): array
{
    return [
        config_path('splitbutton-for-filament.php'),
        lang_path('vendor/splitbutton-for-filament/de/splitbutton.php'),
        resource_path('views/vendor/splitbutton-for-filament/split-button.blade.php'),
        public_path('css/blemli/splitbutton-for-filament/splitbutton.css'),
        public_path('js/blemli/splitbutton-for-filament/components/splitbutton.js'),
        database_path('migrations/2026_01_01_000000_create_splitbutton_layouts_table.php'),
    ];
}

function publishFakeArtifacts(): array
{
    foreach (fakePublishedPaths() as $path) {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, match (true) {
            str_contains($path, 'migrations') => "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration\n{\n    public function up(): void {}\n};\n",
            str_ends_with($path, '.php') && ! str_contains($path, 'views') => '<?php return [];',
            default => '',
        });
    }

    return fakePublishedPaths();
}

function fakeRegistration(): string
{
    $provider = app_path('Providers/Filament/AdminPanelProvider.php');
    File::ensureDirectoryExists(dirname($provider));
    File::put($provider, "<?php\n\n// ...\n\$panel->plugin(SplitButtonPlugin::make());\n");

    return $provider;
}

it('removes every published artifact and drops the table on uninstall', function () {
    $paths = publishFakeArtifacts();

    expect(Schema::hasTable('splitbutton_layouts'))->toBeTrue();

    $this->artisan('splitbutton:uninstall', ['--force' => true])->assertSuccessful();

    foreach ($paths as $path) {
        expect(File::exists($path))->toBeFalse($path . ' should have been removed');
    }

    expect(Schema::hasTable('splitbutton_layouts'))->toBeFalse()
        ->and(File::exists(public_path('css/blemli')))->toBeFalse()
        ->and(File::exists(public_path('js/blemli')))->toBeFalse();
});

it('leaves the assets of other blemli plugins alone', function () {
    publishFakeArtifacts();
    File::ensureDirectoryExists(public_path('css/blemli/other-for-filament'));
    File::put(public_path('css/blemli/other-for-filament/other.css'), '');

    $this->artisan('splitbutton:uninstall', ['--force' => true])->assertSuccessful();

    expect(File::exists(public_path('css/blemli/other-for-filament/other.css')))->toBeTrue()
        ->and(File::exists(public_path('css/blemli/splitbutton-for-filament')))->toBeFalse();
});

it('points to panel providers that still register the plugin', function () {
    fakeRegistration();

    $this->artisan('splitbutton:uninstall', ['--force' => true])
        ->expectsOutputToContain('SplitButtonPlugin is still registered')
        ->expectsOutputToContain('AdminPanelProvider.php:4')
        ->assertSuccessful();
});

it('loops until the registration is gone and never edits the provider', function () {
    $provider = fakeRegistration();
    $before = File::get($provider);

    $this->artisan('splitbutton:uninstall')
        ->expectsConfirmation('Drop the splitbutton_layouts table?', 'no')
        ->expectsConfirmation('Removed it?', 'yes')
        ->expectsConfirmation('Removed it?', 'no')
        ->expectsConfirmation('Run "composer remove blemli/splitbutton-for-filament" now?', 'no')
        ->assertSuccessful();

    expect(File::get($provider))->toBe($before);
});

it('exits the loop once the registration disappears', function () {
    $command = new class extends UninstallCommand
    {
        public array $scans = [];

        protected function findPluginRegistrations(): array
        {
            return array_shift($this->scans) ?? [];
        }
    };
    $command->scans = [[app_path('Providers/Filament/AdminPanelProvider.php') . ':4'], []];

    app(Kernel::class)->registerCommand($command);

    $this->artisan('splitbutton:uninstall')
        ->expectsConfirmation('Drop the splitbutton_layouts table?', 'no')
        ->expectsConfirmation('Removed it?', 'yes')
        ->expectsOutputToContain('No SplitButtonPlugin registration left')
        ->expectsConfirmation('Run "composer remove blemli/splitbutton-for-filament" now?', 'no')
        ->assertSuccessful();
});

it('never asks under --force and never runs composer', function () {
    fakeRegistration();

    $this->artisan('splitbutton:uninstall', ['--force' => true])
        ->expectsOutputToContain('Finish with: composer remove blemli/splitbutton-for-filament')
        ->assertSuccessful();
});

it('prints english output regardless of the app locale', function () {
    app()->setLocale('de');
    publishFakeArtifacts();

    $this->artisan('splitbutton:uninstall', ['--force' => true])
        ->expectsOutputToContain('The following will be removed:')
        ->expectsOutputToContain('splitbutton-for-filament was uninstalled. Finish with: composer remove blemli/splitbutton-for-filament')
        ->assertSuccessful();
});

it('runs non-interactively with safe defaults', function () {
    publishFakeArtifacts();

    $this->artisan('splitbutton:uninstall', ['--no-interaction' => true])
        ->expectsOutputToContain('Finish with: composer remove blemli/splitbutton-for-filament')
        ->assertSuccessful();

    expect(File::exists(config_path('splitbutton-for-filament.php')))->toBeFalse()
        ->and(Schema::hasTable('splitbutton_layouts'))->toBeTrue();
});

it('drops the table non-interactively when told to', function () {
    $this->artisan('splitbutton:uninstall', ['--no-interaction' => true, '--drop-tables' => true])->assertSuccessful();

    expect(Schema::hasTable('splitbutton_layouts'))->toBeFalse();
});
