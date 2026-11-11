<?php

namespace Blemli\SplitButton\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Blemli\SplitButton\SplitButtonManager;
use Blemli\SplitButton\SplitButtonPlugin;
use Blemli\SplitButton\SplitButtonServiceProvider;
use Blemli\SplitButton\Tests\Fixtures\AdminPanelProvider;
use Blemli\SplitButton\Tests\Fixtures\SchemaHost;
use Blemli\SplitButton\Tests\Fixtures\SplitHost;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Blemli\SplitButton\Tests\Fixtures\User;
use Blemli\SplitButton\Tests\Fixtures\UsersTable;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Facades\File;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        TestPage::$headerActions = null;
        UsersTable::$recordActions = null;
        UsersTable::$headerActions = null;
        UsersTable::$toolbarActions = null;
        UsersTable::$emptyStateActions = null;
        UsersTable::$empty = false;
        SchemaHost::$form = null;
        SchemaHost::$infolist = null;
        SchemaHost::$open = null;
        SplitHost::$buttons = null;
    }

    protected function tearDown(): void
    {
        // Anything the suite writes into the shared testbench skeleton must
        // go — leftovers poison every later test.
        foreach ([
            config_path('splitbutton-for-filament.php'),
            lang_path('vendor/splitbutton-for-filament'),
            resource_path('views/vendor/splitbutton-for-filament'),
            public_path('css/blemli'),
            public_path('js/blemli'),
            app_path('Providers/Filament'),
            ...File::glob(database_path('migrations/*_create_splitbutton_layouts_table.php')),
        ] as $path) {
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            SplitButtonServiceProvider::class,
        ];

        sort($providers);

        return [...$providers, AdminPanelProvider::class];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.locale', 'en');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        (require __DIR__ . '/../database/migrations/create_splitbutton_layouts_table.php.stub')->up();
    }

    /**
     * Sign a user in on the admin panel — what every per-user feature needs.
     */
    protected function signIn(?User $user = null): User
    {
        $user ??= User::query()->create([
            'name' => 'Ada',
            'email' => uniqid('ada') . '@example.com',
            'password' => 'secret',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);

        app(SplitButtonManager::class)->forgetCached();

        return $user;
    }

    /**
     * The panel's plugin instance, to switch single features on per test.
     */
    protected function plugin(): SplitButtonPlugin
    {
        /** @var SplitButtonPlugin $plugin */
        $plugin = Filament::getPanel('admin')->getPlugin('splitbutton-for-filament');

        return $plugin;
    }
}
