<?php

namespace Blemli\SplitButton\Commands;

use Blemli\SplitButton\SplitButtonPlugin;
use Blemli\SplitButton\Support\PanelRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    public $signature = 'splitbutton:install
        {--migration : Publish the splitbutton_layouts migration, needed for ->persist() (default: no)}
        {--run-migrations : Run the migrations after publishing (default: yes when the migration is published interactively, no under --no-interaction)}
        {--panel= : Register the plugin in this panel\'s provider, by panel id (default: asks; no file is touched under --no-interaction)}';

    /**
     * The name spatie's package tools would have given it: both work.
     *
     * @var array<string>
     */
    protected $aliases = ['splitbutton-for-filament:install'];

    public $description = 'Install splitbutton-for-filament: publish its config and, for ->persist(), its migration, and register the plugin in a panel provider';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', ['--tag' => 'splitbutton-for-filament-config']);
        $this->info('Published config/splitbutton-for-filament.php.');

        $publishMigration = $this->option('migration')
            || $this->option('run-migrations')
            || $this->confirmOrDefault('Store menus per user in the database (->persist())? This publishes a migration.', false);

        if ($publishMigration) {
            $this->callSilently('vendor:publish', ['--tag' => 'splitbutton-for-filament-migrations']);
            $this->info('Published the splitbutton_layouts migration.');

            if ($this->option('run-migrations') || $this->confirmOrDefault('Run the migrations now?', true, interactiveOnly: true)) {
                $this->call('migrate');
            } else {
                $this->line('Run it with: php artisan migrate');
            }
        }

        $this->registerPlugin('SplitButtonPlugin::make()' . ($publishMigration ? '->persist()' : ''));

        return self::SUCCESS;
    }

    /**
     * Write the registration into the panel provider the developer picks
     * (--panel, or a prompt). Whenever that is not possible, or not
     * wanted, say what to add by hand — never guess.
     */
    protected function registerPlugin(string $call): void
    {
        $hint = "Register the plugin in your panel provider: ->plugins([{$call}])";
        $providers = $this->findPanelProviders();
        $provider = $this->pickPanelProvider($providers);

        if ($provider === null) {
            if (filled($this->option('panel'))) {
                $this->warn("No panel provider with the id [{$this->option('panel')}] found in app/Providers.");
            }

            $this->info($hint);

            return;
        }

        $path = $this->relativePath($provider);
        $code = File::get($provider);

        if (str_contains($code, 'SplitButtonPlugin')) {
            $this->info("The plugin is already registered in {$path}.");

            if (str_contains($call, '->persist()') && (! str_contains($code, '->persist()'))) {
                $this->line('Add ->persist() to it to store the menus in the database.');
            }

            return;
        }

        $registered = PanelRegistrar::register($code, SplitButtonPlugin::class, $call);

        if ($registered === null) {
            $this->warn("Could not find where the plugins go in {$path}; it was left as it is.");
            $this->info($hint);

            return;
        }

        File::put($provider, $registered);
        $this->info("Registered {$call} in {$path}.");
    }

    /**
     * @param  array<string, string | null>  $providers  panel id by path
     */
    protected function pickPanelProvider(array $providers): ?string
    {
        if (filled($panel = $this->option('panel'))) {
            return array_search($panel, $providers, true) ?: null;
        }

        if (($providers === []) || (! $this->input->isInteractive())) {
            return null;
        }

        if (count($providers) === 1) {
            $provider = array_key_first($providers);

            return confirm("Register the plugin in {$this->relativePath($provider)}?", default: true) ? $provider : null;
        }

        $none = 'None, I will do it myself';

        $choice = select(
            'Register the plugin in which panel provider?',
            [...array_map($this->relativePath(...), array_keys($providers)), $none],
        );

        foreach (array_keys($providers) as $provider) {
            if ($this->relativePath($provider) === $choice) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * The app's panel providers, with the id each gives its panel.
     *
     * @return array<string, string | null>
     */
    protected function findPanelProviders(): array
    {
        $providers = [];

        if (! File::isDirectory(app_path('Providers'))) {
            return $providers;
        }

        foreach (File::allFiles(app_path('Providers')) as $file) {
            $code = File::get($file->getPathname());

            if (str_contains($code, 'extends PanelProvider')) {
                $providers[$file->getPathname()] = PanelRegistrar::panelId($code);
            }
        }

        ksort($providers);

        return $providers;
    }

    protected function relativePath(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
    }

    /**
     * Non-interactive runs (-n, CI, deploy scripts) get the default without
     * a prompt; migrations additionally never run unasked.
     */
    protected function confirmOrDefault(string $question, bool $default, bool $interactiveOnly = false): bool
    {
        if (! $this->input->isInteractive()) {
            return $interactiveOnly ? false : $default;
        }

        return confirm($question, default: $default);
    }
}
