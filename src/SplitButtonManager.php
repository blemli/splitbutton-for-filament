<?php

namespace Blemli\SplitButton;

use Blemli\SplitButton\Storage\DatabaseStore;
use Blemli\SplitButton\Storage\LayoutStore;
use Blemli\SplitButton\Storage\SessionStore;
use Filament\Facades\Filament;
use Throwable;

/**
 * Resolves the plugin of the current panel, the layout store and each
 * button's stored layout. Null-safe throughout: without the plugin on the
 * panel a SplitButton is simply static.
 */
class SplitButtonManager
{
    /** @var array<string, Layout> */
    protected array $layouts = [];

    public function plugin(): ?SplitButtonPlugin
    {
        try {
            $panel = Filament::getCurrentPanel();

            if (! $panel?->hasPlugin('splitbutton-for-filament')) {
                return null;
            }

            $plugin = $panel->getPlugin('splitbutton-for-filament');

            return $plugin instanceof SplitButtonPlugin ? $plugin : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function panelId(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Per-user state needs the plugin (for its routes) and a signed-in
     * user who is allowed to customize.
     */
    public function canCustomize(): bool
    {
        return (bool) $this->plugin()?->isAuthorized();
    }

    public function store(): LayoutStore
    {
        return $this->plugin()?->isPersistent()
            ? app(DatabaseStore::class)
            : app(SessionStore::class);
    }

    public function layout(string $key): Layout
    {
        return $this->layouts[$key] ??= $this->store()->get($key);
    }

    public function save(string $key, Layout $layout): void
    {
        $layout->isEmpty()
            ? $this->store()->forget($key)
            : $this->store()->put($key, $layout);

        $this->layouts[$key] = $layout;
    }

    public function reset(string $key): void
    {
        $this->store()->forget($key);

        $this->layouts[$key] = new Layout;
    }

    public function forgetCached(?string $key = null): void
    {
        if ($key === null) {
            $this->layouts = [];

            return;
        }

        unset($this->layouts[$key]);
    }

    /**
     * URL of one of the plugin's panel routes, or null when the plugin is
     * not registered on the current panel.
     */
    public function url(string $route): ?string
    {
        if ($this->plugin() === null) {
            return null;
        }

        try {
            return Filament::getCurrentPanel()?->route("splitbutton.{$route}", absolute: false);
        } catch (Throwable) {
            return null;
        }
    }
}
