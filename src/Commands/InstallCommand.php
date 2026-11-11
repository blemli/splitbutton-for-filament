<?php

namespace Blemli\SplitButton\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    public $signature = 'splitbutton-for-filament:install
        {--migration : Publish the splitbutton_layouts migration, needed for ->persist() (default: no)}
        {--run-migrations : Run the migrations after publishing (default: yes when the migration is published interactively, no under --no-interaction)}';

    public $description = 'Install splitbutton-for-filament: publish its config and, for ->persist(), its migration';

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

        $this->info('Register the plugin in your panel provider: ->plugin(SplitButtonPlugin::make())');

        return self::SUCCESS;
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
