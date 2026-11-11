<?php

namespace Blemli\SplitButton\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;

class UninstallCommand extends Command
{
    public $signature = 'splitbutton:uninstall
        {--force : Answer every prompt with yes, except the composer step (never runs under --force)}
        {--drop-tables : Drop the splitbutton_layouts table (default: no)}
        {--remove-composer : Run "composer remove blemli/splitbutton-for-filament" afterwards (default: no)}';

    /**
     * The package name works as a prefix too, as for the installer.
     *
     * @var array<string>
     */
    protected $aliases = ['splitbutton-for-filament:uninstall'];

    public $description = 'Uninstall splitbutton-for-filament: drop its table and remove published files';

    public function handle(): int
    {
        $this->removeTable();
        $this->removePublishedFiles();

        $registrations = $this->findPluginRegistrations();

        if ($this->option('force') || ! $this->input->isInteractive()) {
            $this->warnAboutRegistrations($registrations);
        } else {
            while ($registrations !== []) {
                $this->warnAboutRegistrations($registrations);

                if (! confirm('Removed it?', default: false)) {
                    break;
                }

                $registrations = $this->findPluginRegistrations();
            }

            if ($registrations === []) {
                $this->info('No SplitButtonPlugin registration left.');
            }
        }

        $removeComposer = $this->option('remove-composer')
            || (! $this->option('force') && $this->confirmOrDefault('Run "composer remove blemli/splitbutton-for-filament" now?', $registrations === [], interactiveOnly: true));

        if ($removeComposer) {
            Process::path(base_path())
                ->forever()
                ->run(['composer', 'remove', 'blemli/splitbutton-for-filament'], fn (string $type, string $output) => $this->output->write($output));

            $this->info('splitbutton-for-filament was uninstalled.');
        } else {
            $this->info('splitbutton-for-filament was uninstalled. Finish with: composer remove blemli/splitbutton-for-filament');
        }

        $this->line('SplitButton::make(...) and ->split() calls in your pages must go as well, or they will fail once the package is removed.');

        return self::SUCCESS;
    }

    protected function layoutsTable(): string
    {
        return (string) config('splitbutton-for-filament.table', 'splitbutton_layouts');
    }

    protected function removeTable(): void
    {
        $table = $this->layoutsTable();

        if (! Schema::hasTable($table)) {
            return;
        }

        $this->info("The {$table} table (every user's menu layouts) will be dropped.");

        if ($this->option('drop-tables') || $this->option('force') || $this->confirmOrDefault("Drop the {$table} table?", false)) {
            Schema::drop($table);
            $this->info("Dropped {$table}.");
        }
    }

    protected function removePublishedFiles(): void
    {
        $paths = array_filter([
            config_path('splitbutton-for-filament.php'),
            lang_path('vendor/splitbutton-for-filament'),
            resource_path('views/vendor/splitbutton-for-filament'),
            public_path('css/blemli/splitbutton-for-filament'),
            public_path('js/blemli/splitbutton-for-filament'),
            ...File::glob(database_path('migrations/*_create_splitbutton_layouts_table.php')),
        ], fn (string $path): bool => File::exists($path));

        if ($paths === []) {
            $this->info('Nothing published to remove.');

            return;
        }

        $this->info('The following will be removed:');

        foreach ($paths as $path) {
            $this->line("  - {$path}");
        }

        if (! $this->option('force') && ! $this->confirmOrDefault('Delete published config, translations, views, assets and migrations?', true)) {
            return;
        }

        foreach ($paths as $path) {
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        // Filament publishes into a shared vendor folder; leave no empty shell.
        foreach ([public_path('css/blemli'), public_path('js/blemli')] as $parent) {
            if (File::isDirectory($parent) && File::isEmptyDirectory($parent)) {
                File::deleteDirectory($parent);
            }
        }
    }

    /**
     * Non-interactive runs (-n, CI, deploy scripts) get the default without
     * a prompt; the composer step additionally never runs unasked.
     */
    protected function confirmOrDefault(string $question, bool $default, bool $interactiveOnly = false): bool
    {
        if (! $this->input->isInteractive()) {
            return $interactiveOnly ? false : $default;
        }

        return confirm($question, default: $default);
    }

    /**
     * @param  array<string>  $registrations
     */
    protected function warnAboutRegistrations(array $registrations): void
    {
        if ($registrations === []) {
            return;
        }

        $this->warn('SplitButtonPlugin is still registered in your panel provider(s) — remove the ->plugin(SplitButtonPlugin::make()...) call or the app will crash after composer remove:');

        foreach ($registrations as $location) {
            $this->line("  - {$location}");
        }
    }

    /**
     * Find SplitButtonPlugin registrations in the app's providers so the
     * user can remove them before the package code disappears. The file is
     * never edited automatically — that is the user's code.
     *
     * @return array<string>
     */
    protected function findPluginRegistrations(): array
    {
        $locations = [];

        if (! File::isDirectory(app_path('Providers'))) {
            return $locations;
        }

        foreach (File::allFiles(app_path('Providers')) as $file) {
            foreach (explode("\n", File::get($file->getPathname())) as $index => $line) {
                if (str_contains($line, 'SplitButtonPlugin')) {
                    $locations[] = $file->getPathname() . ':' . ($index + 1);
                }
            }
        }

        return $locations;
    }
}
