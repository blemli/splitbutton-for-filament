<?php

namespace Blemli\SplitButton;

use Blemli\SplitButton\Enums\AutoPin;
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

    protected bool | Closure $isEditableWithMenuEntry = true;

    protected bool | Closure $isEditableWithLongPress = false;

    protected bool | Closure $isEditableWithKeyboard = true;

    /** @var AutoPin | string | array<AutoPin | string> | bool | Closure */
    protected AutoPin | string | array | bool | Closure $autoPin = false;

    protected AutoPin | string | bool $autoPinByDefault = false;

    protected bool $isAutoPinOptional = false;

    protected ?float $frecencyHalfLife = null;

    protected bool | Closure $hasFormSettingsPresets = false;

    protected bool | string | Closure $authorization = true;

    protected ?int $longPressDelay = null;

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
     * Let users rearrange every split button: the "Customize" entry at the
     * end of the menu (or E on the keyboard) enters edit mode to reorder,
     * hide, pick the default and reset.
     */
    public function editable(bool | Closure $condition = true): static
    {
        $this->isEditable = $condition;

        return $this;
    }

    /**
     * The visible "Customize" entry at the end of editable menus: the way
     * into edit mode everybody finds. On unless you pass false — then make
     * sure users have another way in (->editableWithLongPress(), or E).
     */
    public function editableWithMenuEntry(bool | Closure $condition = true): static
    {
        $this->isEditableWithMenuEntry = $condition;

        return $this;
    }

    /**
     * Also enter edit mode by holding the menu or one of its entries, the
     * way a phone's home screen is rearranged. Off unless you ask for it.
     */
    public function editableWithLongPress(bool | Closure $condition = true): static
    {
        $this->isEditableWithLongPress = $condition;

        return $this;
    }

    /**
     * The keyboard shortcuts of edit mode: E to enter it, Ctrl / ⌘ with the
     * arrows to move, H to hide, D to pin. On unless you pass false, for
     * panels whose single keys belong to something else (filament-mouseless
     * badges still reach every control). Arrows, Enter and Escape keep
     * working either way: those are how a menu is operated, not shortcuts.
     */
    public function editableWithKeyboard(bool | Closure $condition = true): static
    {
        $this->isEditableWithKeyboard = $condition;

        return $this;
    }

    /**
     * Every button follows its user's picks: what they picked last
     * (Recent), most often (Frequent) or most often lately (Frecent, the
     * default) sits on the button. A pin a user sets by hand in edit mode
     * still wins; taking it off hands the button back to the strategy.
     */
    public function autoPin(AutoPin | string | bool | Closure $strategy = AutoPin::Frecent): static
    {
        $this->autoPin = $strategy;
        $this->isAutoPinOptional = false;
        $this->autoPinByDefault = false;

        return $this;
    }

    /**
     * Leave it to each user instead: in edit mode they get a switch that
     * makes the button follow their picks — one per strategy when you
     * offer several. Nothing changes for a user until they turn one on,
     * unless byDefault says so: true for the first strategy, or the
     * strategy itself.
     *
     * @param  AutoPin | string | array<AutoPin | string> | bool | Closure  $strategies
     */
    public function offerAutoPin(AutoPin | string | array | bool | Closure $strategies = AutoPin::Frecent, AutoPin | string | bool $byDefault = false): static
    {
        $this->autoPin = $strategies;
        $this->isAutoPinOptional = true;
        $this->autoPinByDefault = $byDefault;

        return $this;
    }

    /**
     * How fast old picks fade for AutoPin::Frecent: after this many days a
     * pick counts half. Short follows the user's week, long their habits.
     */
    public function frecencyHalfLife(int | float $days): static
    {
        $this->frecencyHalfLife = max(0.01, (float) $days);

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
     * Milliseconds the menu must be held for ->editableWithLongPress().
     */
    public function longPressDelay(int $milliseconds): static
    {
        $this->longPressDelay = max(150, $milliseconds);

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

    public function isEditableWithMenuEntry(): bool
    {
        return $this->evaluate($this->isEditableWithMenuEntry);
    }

    public function isEditableWithLongPress(): bool
    {
        return $this->evaluate($this->isEditableWithLongPress);
    }

    public function isEditableWithKeyboard(): bool
    {
        return $this->evaluate($this->isEditableWithKeyboard);
    }

    /**
     * @return array<AutoPin>
     */
    public function getAutoPinStrategies(): array
    {
        return AutoPin::list($this->autoPin instanceof Closure ? ($this->autoPin)() : $this->autoPin);
    }

    public function isAutoPinOptional(): bool
    {
        return $this->isAutoPinOptional;
    }

    public function getAutoPinByDefault(): AutoPin | string | bool
    {
        return $this->autoPinByDefault;
    }

    /**
     * In days.
     */
    public function getFrecencyHalfLife(): float
    {
        return $this->frecencyHalfLife ?? max(0.01, (float) config('splitbutton-for-filament.frecency_half_life', 14));
    }

    public function hasFormSettingsPresets(): bool
    {
        return $this->evaluate($this->hasFormSettingsPresets);
    }

    public function getLongPressDelay(): int
    {
        return $this->longPressDelay ?? max(150, (int) config('splitbutton-for-filament.long_press_delay', 500));
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
