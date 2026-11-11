<?php

namespace Blemli\SplitButton;

use Blemli\SplitButton\Http\Controllers\LayoutController;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Panel-wide defaults for every SplitButton. Everything is opt-in: a bare
 * SplitButtonPlugin::make() only registers the routes the per-user features
 * need. Each feature can also be switched on (or off) on a single button.
 */
class SplitButtonPlugin implements Plugin
{
    protected bool | Closure $isPersistent = false;

    protected bool | Closure $isEditable = false;

    protected bool | Closure $hasEditHint = false;

    protected bool | Closure $remembersLastUsed = false;

    protected bool $remembersLastUsedByDefault = false;

    protected bool | Closure $hasFormSettingsPresets = false;

    protected bool | string | Closure $authorization = true;

    protected ?int $longPress = null;

    public function getId(): string
    {
        return 'splitbutton-for-filament';
    }

    /**
     * Store each user's menus in the database (same menu on every device
     * and in every session) instead of the session. Needs the migration.
     */
    public function persist(bool | Closure $condition = true): static
    {
        $this->isPersistent = $condition;

        return $this;
    }

    /**
     * Let users rearrange every split button: a long press on the menu (or
     * E on the keyboard) enters edit mode to reorder, hide, pick the
     * default and reset.
     */
    public function editable(bool | Closure $condition = true): static
    {
        $this->isEditable = $condition;

        return $this;
    }

    /**
     * Show a visible "Customize" entry at the end of editable menus, for
     * users who would never discover the long press.
     */
    public function editHint(bool | Closure $condition = true): static
    {
        $this->hasEditHint = $condition;

        return $this;
    }

    /**
     * Offer users a "last used on the button" toggle in edit mode: when a
     * user turns it on, the entry they picked last becomes the button face.
     * Pass byDefault: true to start with the toggle on.
     */
    public function rememberLastUsed(bool | Closure $condition = true, bool $byDefault = false): static
    {
        $this->remembersLastUsed = $condition;
        $this->remembersLastUsedByDefault = $byDefault;

        return $this;
    }

    /**
     * List the user's formsettings-for-filament presets marked as quick
     * create / quick edit in split buttons whose first action is a
     * resource's CreateAction / EditAction. Inert when that plugin is not
     * installed.
     */
    public function formSettingsPresets(bool | Closure $condition = true): static
    {
        $this->hasFormSettingsPresets = $condition;

        return $this;
    }

    /**
     * Restrict who may customize their menus. Accepts a boolean, a closure
     * receiving the authenticated user (or null), or a Gate ability name.
     */
    public function authorize(bool | string | Closure $condition): static
    {
        $this->authorization = $condition;

        return $this;
    }

    /**
     * Milliseconds the menu must be pressed to enter edit mode.
     */
    public function longPress(int $milliseconds): static
    {
        $this->longPress = max(150, $milliseconds);

        return $this;
    }

    public function isPersistent(): bool
    {
        return $this->evaluate($this->isPersistent);
    }

    public function isEditable(): bool
    {
        return $this->evaluate($this->isEditable);
    }

    public function hasEditHint(): bool
    {
        return $this->evaluate($this->hasEditHint);
    }

    public function remembersLastUsed(): bool
    {
        return $this->evaluate($this->remembersLastUsed);
    }

    public function remembersLastUsedByDefault(): bool
    {
        return $this->remembersLastUsedByDefault;
    }

    public function hasFormSettingsPresets(): bool
    {
        return $this->evaluate($this->hasFormSettingsPresets);
    }

    public function getLongPress(): int
    {
        return $this->longPress ?? max(150, (int) config('splitbutton-for-filament.long_press', 500));
    }

    public function isAuthorized(): bool
    {
        try {
            $user = Filament::auth()->user();
        } catch (Throwable) {
            $user = auth()->user();
        }

        if ($user === null) {
            return false;
        }

        if ($this->authorization instanceof Closure) {
            return (bool) ($this->authorization)($user);
        }

        if (is_string($this->authorization)) {
            return (bool) $user->can($this->authorization);
        }

        return $this->authorization;
    }

    public function register(Panel $panel): void
    {
        $panel->authenticatedRoutes(function (): void {
            Route::post('splitbutton/layout', [LayoutController::class, 'update'])->name('splitbutton.layout');
            Route::post('splitbutton/used', [LayoutController::class, 'used'])->name('splitbutton.used');
        });
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    protected function evaluate(bool | Closure $value): bool
    {
        return (bool) ($value instanceof Closure ? $value() : $value);
    }
}
