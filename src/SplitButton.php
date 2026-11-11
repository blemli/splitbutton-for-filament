<?php

namespace Blemli\SplitButton;

use BackedEnum;
use Blemli\SplitButton\Enums\TriggerPosition;
use Blemli\SplitButton\Support\FormSettingsBridge;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Size;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use Throwable;
use Traversable;

/**
 * A button joined to a menu of its variants — or, with ->overflow(), a row
 * of buttons with the rest tucked under "More".
 *
 * It is an ActionGroup, so it goes wherever Filament takes one (page
 * headers, tables, forms, infolists) and every entry is a real Action with
 * everything a Filament button does. The first action is the button; the
 * others are its menu. What a user did to the menu (order, hidden entries,
 * the default on the button face, last used) is applied when it renders.
 */
class SplitButton extends ActionGroup
{
    public const VIEW = 'splitbutton-for-filament::split-button';

    /** @var array<Closure> */
    protected array $optionSources = [];

    protected bool $isMaterialized = false;

    protected bool | Closure | null $isEditable = null;

    protected bool | Closure | null $hasEditHint = null;

    protected bool | Closure | null $remembersLastUsed = null;

    protected ?bool $remembersLastUsedByDefault = null;

    protected bool | Closure | null $hasFormSettingsPresets = null;

    /** @var class-string | null */
    protected ?string $formSettingsResource = null;

    protected ?string $formSettingsOperation = null;

    protected ?string $stateKey = null;

    protected ?string $declaredName = null;

    protected bool $isOverflow = false;

    protected int | Closure | null $primaryCount = null;

    protected TriggerPosition | string | null $triggerPosition = null;

    protected bool | Closure | null $shouldFilterUnauthorized = null;

    /** @var array<string, mixed> | null */
    protected ?array $composition = null;

    /**
     * @param  array<Action | ActionGroup>  $actions
     */
    public static function make(array $actions = []): static
    {
        return parent::make($actions);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->view(static::VIEW);
        $this->defaultTriggerView(static::BUTTON_VIEW);
    }

    /**
     * @param  array<Action | ActionGroup>  $actions
     */
    public function actions(array $actions): static
    {
        parent::actions($actions);

        $this->composition = null;

        foreach ($actions as $action) {
            if ($action instanceof Action) {
                $this->declaredName ??= $action->getName();

                break;
            }
        }

        return $this;
    }

    /**
     * More menu entries: an array of actions, or a closure returning them
     * (evaluated per request with $livewire, $record, … injected — e.g. the
     * three most used templates). In a table the closure runs once for the
     * whole table, before any row: make single entries depend on the row
     * with ->visible(fn ($record) => …) instead.
     *
     * @param  array<Action | ActionGroup> | Closure  $options
     */
    public function options(array | Closure $options): static
    {
        if ($options instanceof Closure) {
            $this->optionSources[] = $options;
            $this->isMaterialized = false;

            return $this;
        }

        return $this->actions([...$this->actions, ...$options]);
    }

    /**
     * Let users rearrange this menu: a long press on it (or E) enters edit
     * mode to reorder, hide, pick the default and reset. Needs the plugin
     * on the panel.
     */
    public function editable(bool | Closure $condition = true): static
    {
        $this->isEditable = $condition;

        return $this;
    }

    /**
     * Show a visible "Customize" entry at the end of the editable menu.
     */
    public function editHint(bool | Closure $condition = true): static
    {
        $this->hasEditHint = $condition;

        return $this;
    }

    /**
     * Offer the "last used on the button" toggle in edit mode. Pass
     * byDefault: true to start with it switched on.
     */
    public function rememberLastUsed(bool | Closure $condition = true, ?bool $byDefault = null): static
    {
        $this->remembersLastUsed = $condition;
        $this->remembersLastUsedByDefault = $byDefault;

        return $this;
    }

    /**
     * List the user's formsettings-for-filament presets marked as quick
     * create / quick edit. Resource and operation are derived from the page
     * and the first action (CreateAction → create, EditAction → edit)
     * unless given. Inert when that plugin is not installed.
     *
     * @param  class-string | null  $resource
     */
    public function formSettingsPresets(bool | Closure $condition = true, ?string $resource = null, ?string $operation = null): static
    {
        $this->hasFormSettingsPresets = $condition;
        $this->formSettingsResource = $resource;
        $this->formSettingsOperation = $operation;
        $this->isMaterialized = false;

        return $this;
    }

    /**
     * Identifies this button's stored layout. Defaults to the name of the
     * first action; set it when two split buttons on one page start with
     * the same action.
     */
    public function key(?string $key): static
    {
        $this->stateKey = $key;

        return $this;
    }

    /**
     * Instead of one button joined to its menu, keep a few buttons out
     * front and tuck the rest under "More". With nothing left over there is
     * no "More"; a single leftover is shown as a button of its own.
     */
    public function overflow(int | Closure | null $primaryCount = null, bool $condition = true): static
    {
        if (is_int($primaryCount) && $primaryCount < 0) {
            throw new InvalidArgumentException('The primary count of a split button cannot be negative.');
        }

        $this->isOverflow = $condition;
        $this->primaryCount = $primaryCount;
        $this->composition = null;

        return $this;
    }

    /**
     * Put the menu trigger before or after the button(s). Logical: Start is
     * the reading start, so it flips under RTL on its own.
     */
    public function triggerPosition(TriggerPosition | string | null $position): static
    {
        $this->triggerPosition = $position;

        return $this;
    }

    /**
     * Drop entries the user is not authorized for, instead of leaving it to
     * Filament to disable or hide them.
     */
    public function filterUnauthorized(bool | Closure $condition = true): static
    {
        $this->shouldFilterUnauthorized = $condition;

        return $this;
    }

    public function isOverflow(): bool
    {
        return $this->isOverflow;
    }

    public function getPrimaryCount(): int
    {
        if (! $this->isOverflow) {
            return 1;
        }

        $count = $this->evaluate($this->primaryCount) ?? (int) config('splitbutton-for-filament.overflow.primary_count', 1);

        return max(0, (int) $count);
    }

    public function getTriggerPosition(): TriggerPosition
    {
        $position = $this->triggerPosition ?? config('splitbutton-for-filament.trigger_position', 'end');

        return $position instanceof TriggerPosition
            ? $position
            : (TriggerPosition::tryFrom((string) $position) ?? TriggerPosition::End);
    }

    /**
     * The menu opens under the control, along the edge its trigger is on:
     * bottom-end with the trigger at the end, bottom-start with it at the
     * start (both follow the reading direction). Answering here, rather
     * than in the view, keeps Filament from forcing its own page-header
     * default onto a trigger that sits at the start. An explicit
     * ->dropdownPlacement() always wins.
     */
    public function getDropdownPlacement(): ?string
    {
        $explicit = $this->evaluate($this->dropdownPlacement);

        if (filled($explicit)) {
            return $explicit;
        }

        $own = $this->getTriggerPosition() === TriggerPosition::Start ? 'bottom-start' : 'bottom-end';

        // A "More" trigger stands on its own, so a table may still say where
        // its menus open; the chevron of a split button may not be pulled
        // away from the button it is joined to.
        return $this->isOverflow
            ? ($this->evaluate($this->defaultDropdownPlacement) ?? $own)
            : $own;
    }

    public function shouldFilterUnauthorized(): bool
    {
        return (bool) ($this->evaluate($this->shouldFilterUnauthorized) ?? config('splitbutton-for-filament.filter_unauthorized', false));
    }

    public function isEditable(): bool
    {
        return (bool) ($this->evaluate($this->isEditable) ?? $this->manager()->plugin()?->isEditable() ?? false);
    }

    public function hasEditHint(): bool
    {
        return (bool) ($this->evaluate($this->hasEditHint) ?? $this->manager()->plugin()?->hasEditHint() ?? false);
    }

    public function remembersLastUsed(): bool
    {
        return (bool) ($this->evaluate($this->remembersLastUsed) ?? $this->manager()->plugin()?->remembersLastUsed() ?? false);
    }

    public function remembersLastUsedByDefault(): bool
    {
        return $this->remembersLastUsedByDefault ?? $this->manager()->plugin()?->remembersLastUsedByDefault() ?? false;
    }

    public function hasFormSettingsPresets(): bool
    {
        return (bool) ($this->evaluate($this->hasFormSettingsPresets) ?? $this->manager()->plugin()?->hasFormSettingsPresets() ?? false);
    }

    /**
     * @return class-string | null
     */
    public function getFormSettingsResource(): ?string
    {
        return $this->formSettingsResource;
    }

    public function getFormSettingsOperation(): ?string
    {
        return $this->formSettingsOperation;
    }

    /**
     * The action the developer declared first — the button, as long as the
     * user did not choose another one.
     */
    public function getDeclaredName(): ?string
    {
        return $this->declaredName;
    }

    /**
     * Per-user state is kept per button, not per record: panel, the
     * Livewire component the button lives on, and the button's key.
     */
    public function getStateKey(): ?string
    {
        $identifier = $this->stateKey ?? $this->declaredName;

        if (($identifier === null) || (($livewire = $this->resolveLivewire()) === null)) {
            return null;
        }

        return ($this->manager()->panelId() ?? 'default')
            . '::' . str_replace('\\', '.', $livewire::class)
            . '::' . $identifier;
    }

    /**
     * Whether this button reads (and may write) a user's layout: one of the
     * per-user features is on, the plugin is on the panel and the user is
     * allowed to customize.
     */
    public function isStateful(): bool
    {
        return ($this->isEditable() || $this->remembersLastUsed())
            && $this->manager()->canCustomize()
            && ($this->getStateKey() !== null);
    }

    public function getLayout(): ?Layout
    {
        if (! $this->isStateful()) {
            return null;
        }

        return $this->manager()->layout((string) $this->getStateKey());
    }

    /**
     * @return array<Action | ActionGroup>
     */
    public function getActions(): array
    {
        $this->materialize();

        return parent::getActions();
    }

    /**
     * @return array<string, Action>
     */
    public function getFlatActions(): array
    {
        $this->materialize();

        return parent::getFlatActions();
    }

    public function getClone(): static
    {
        $clone = parent::getClone();
        $clone->composition = null;

        return $clone;
    }

    /**
     * Resolve the closure options (and formsettings presets) into real
     * child actions, once the button is attached to its page: only then
     * are $livewire and friends available, and Filament flattens the group
     * right after attaching it, so the children resolve when clicked.
     */
    protected function materialize(): void
    {
        if ($this->isMaterialized || ($this->resolveLivewire() === null)) {
            return;
        }

        $this->isMaterialized = true;

        $extra = [];

        foreach ($this->optionSources as $source) {
            $resolved = $this->evaluate($source);

            if ($resolved instanceof Traversable) {
                $resolved = iterator_to_array($resolved, preserve_keys: false);
            }

            foreach ((array) $resolved as $action) {
                if (($action instanceof Action) || ($action instanceof ActionGroup)) {
                    $extra[] = $action;
                }
            }
        }

        if ($this->hasFormSettingsPresets()) {
            $extra = [...$extra, ...FormSettingsBridge::actions($this)];
        }

        if ($extra === []) {
            return;
        }

        $known = parent::getFlatActions();

        // A closure must never replace a declared action of the same name.
        $extra = array_filter(
            $extra,
            fn (Action | ActionGroup $action): bool => ! (($action instanceof Action) && array_key_exists($action->getName(), $known)),
        );

        parent::actions([...$this->actions, ...$extra]);
    }

    /**
     * What to draw: the button face(s), the menu sections, and (for edit
     * mode) every available entry with the user's layout applied.
     *
     * @return array{
     *     faces: array<Action>,
     *     sections: array<array<Action | ActionGroup>>,
     *     entries: array<array<array{name: string, label: string, hidden: bool, pinnable: bool}>>,
     *     face: string | null,
     *     declared: string | null,
     *     hasTrigger: bool,
     *     hasHidden: bool,
     * }
     */
    public function compose(): array
    {
        if ($this->composition !== null) {
            return $this->composition;
        }

        $this->materialize();

        $layout = $this->getLayout();
        $sections = $this->availableSections();

        // Order: the user's order first, anything new after it in declared order.
        if ($layout !== null && $layout->order !== []) {
            $position = array_flip($layout->order);

            foreach ($sections as &$section) {
                $declared = array_flip(array_keys($section));

                uksort($section, fn (string $a, string $b): int => ($position[$a] ?? (PHP_INT_MAX - 1000 + $declared[$a])) <=> ($position[$b] ?? (PHP_INT_MAX - 1000 + $declared[$b])));
            }

            unset($section);
        }

        $hidden = $layout === null ? [] : array_flip($layout->hidden);

        // Only real actions can sit on the button face — never a submenu.
        $candidates = [];

        foreach ($sections as $section) {
            foreach ($section as $name => $entry) {
                if (($entry instanceof Action) && (! isset($hidden[$name]))) {
                    $candidates[$name] = $entry;
                }
            }
        }

        $declared = isset($candidates[$this->declaredName ?? '']) ? $this->declaredName : array_key_first($candidates);
        $pinned = null;

        if ($layout !== null) {
            if ($this->usesLastUsed($layout) && isset($candidates[$layout->lastUsed ?? ''])) {
                $pinned = $layout->lastUsed;
            } elseif (isset($candidates[$layout->default ?? ''])) {
                $pinned = $layout->default;
            }
        }

        $face = $pinned ?? $declared;
        $faces = [];

        if ($this->isOverflow) {
            // The pinned entry leads; the rest keep their (user) order.
            $ordered = $pinned === null ? $candidates : [$pinned => $candidates[$pinned], ...$candidates];
            $faces = array_slice($ordered, 0, $this->getPrimaryCount(), preserve_keys: true);
            $face = $pinned;
        } elseif ($face !== null) {
            $faces = [$face => $candidates[$face]];
        }

        $menu = [];

        foreach ($sections as $section) {
            $visible = array_filter(
                $section,
                fn (string $name): bool => (! isset($hidden[$name])) && (! isset($faces[$name])),
                ARRAY_FILTER_USE_KEY,
            );

            if ($visible !== []) {
                $menu[] = $visible;
            }
        }

        // A single leftover of an overflow row is shown out front, not
        // buried under "More" on its own.
        if ($this->isOverflow && (count($menu) === 1) && (count($menu[0]) === 1) && (reset($menu[0]) instanceof Action)) {
            $faces = [...$faces, ...$menu[0]];
            $menu = [];
        }

        foreach ([$faces, ...$menu] as $entries) {
            foreach ($entries as $name => $entry) {
                if ($entry instanceof Action) {
                    $this->tag($entry, $name);
                }
            }
        }

        $hasHidden = false;
        $entries = [];

        foreach ($sections as $section) {
            $rows = [];

            foreach ($section as $name => $entry) {
                $hasHidden = $hasHidden || isset($hidden[$name]);

                $rows[] = [
                    'name' => $name,
                    'label' => $this->plainLabel($entry),
                    'hidden' => isset($hidden[$name]),
                    'pinnable' => $entry instanceof Action,
                ];
            }

            $entries[] = $rows;
        }

        return $this->composition = [
            'faces' => array_values($faces),
            'sections' => array_map(array_values(...), $menu),
            'entries' => $entries,
            'face' => $face,
            'declared' => $declared,
            // Keep the trigger while entries are hidden, so the user can
            // get back into edit mode and bring them back.
            'hasTrigger' => ($menu !== []) || ($hasHidden && $this->isEditable() && $this->isStateful()),
            'hasHidden' => $hasHidden,
        ];
    }

    /**
     * Draw an entry out front, as the button it would be on its own. An
     * explicit ->link() / ->iconButton() on the action still wins.
     *
     * The style is set right before drawing, never ahead of time: Filament
     * resets every child of a group to "menu item" whenever it asks the
     * group whether it is hidden.
     */
    public function renderFace(Action $action): string
    {
        // ->size() and ->color() on the split button mean the whole control,
        // not just its chevron — unless the action has its own.
        if (! $this->isOverflow) {
            if (($size = parent::getSize()) !== null) {
                $action->defaultSize($size);
            }

            if (($color = parent::getColor()) !== null) {
                $action->defaultColor($color);
            }
        }

        return $action->defaultView(Action::BUTTON_VIEW)->toHtml();
    }

    /**
     * Draw an entry inside the menu: an item, or a submenu for a group.
     */
    public function renderEntry(Action | ActionGroup $entry): string
    {
        return $entry instanceof Action
            ? $entry->defaultView(Action::GROUPED_VIEW)->toHtml()
            : $entry->defaultTriggerView(ActionGroup::GROUPED_VIEW)->toHtml();
    }

    /**
     * The entries the current user may see, grouped the way Filament draws
     * a menu: runs of entries, with every ->dropdown(false) group as a
     * section of its own. Keyed by entry name.
     *
     * @return array<array<string, Action | ActionGroup>>
     */
    protected function availableSections(): array
    {
        $sections = [];
        $run = [];

        foreach ($this->actions as $index => $action) {
            if (($action instanceof ActionGroup) && (! $action->hasDropdown())) {
                if ($run !== []) {
                    $sections[] = $run;
                    $run = [];
                }

                $section = [];

                foreach ($action->getActions() as $childIndex => $child) {
                    if ($this->isAvailable($child)) {
                        $section[$this->entryName($child, "{$index}.{$childIndex}")] = $child;
                    }
                }

                if ($section !== []) {
                    $sections[] = $section;
                }

                continue;
            }

            if ($this->isAvailable($action)) {
                $run[$this->entryName($action, (string) $index)] = $action;
            }
        }

        if ($run !== []) {
            $sections[] = $run;
        }

        return $sections;
    }

    protected function isAvailable(Action | ActionGroup $action): bool
    {
        if ($action->isHidden()) {
            return false;
        }

        if (($action instanceof Action) && $this->shouldFilterUnauthorized()) {
            return $action->isAuthorized();
        }

        return true;
    }

    protected function entryName(Action | ActionGroup $action, string $position): string
    {
        return $action instanceof Action
            ? (string) $action->getName()
            : "group:{$position}";
    }

    protected function tag(Action $action, string $name): void
    {
        $action->extraAttributes(['data-splitbutton-option' => e($name)], merge: true);
    }

    protected function plainLabel(Action | ActionGroup $entry): string
    {
        $label = $entry->getLabel();

        if ($label instanceof Htmlable) {
            $label = $label->toHtml();
        }

        return trim(html_entity_decode(strip_tags((string) $label), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Everything the edit-mode script needs, as the data-splitbutton
     * attribute. Read on demand, so a Livewire morph keeps it current.
     *
     * @return array<string, mixed>
     */
    public function getClientState(): array
    {
        $composition = $this->compose();
        $layout = $this->getLayout();
        $manager = $this->manager();

        return [
            'key' => $this->getStateKey(),
            'editable' => $this->isEditable(),
            'overflow' => $this->isOverflow,
            'sections' => $composition['entries'],
            'pinned' => $layout?->default,
            'declared' => $composition['declared'],
            'canRememberLast' => $this->remembersLastUsed(),
            'rememberLast' => $this->usesLastUsed($layout),
            'longPress' => $manager->plugin()?->getLongPress() ?? 500,
            'urls' => [
                'layout' => $manager->url('layout'),
                'used' => $manager->url('used'),
            ],
        ];
    }

    public function usesLastUsed(?Layout $layout): bool
    {
        if (! $this->remembersLastUsed()) {
            return false;
        }

        return $layout->rememberLast ?? $this->remembersLastUsedByDefault();
    }

    public function getLabel(): string | Htmlable | null
    {
        $label = $this->evaluate($this->label);

        if ($label === null) {
            if ($this->isOverflow && filled($configured = config('splitbutton-for-filament.overflow.label'))) {
                return __($configured);
            }

            return $this->isOverflow
                ? __('splitbutton-for-filament::splitbutton.more')
                : __('splitbutton-for-filament::splitbutton.options');
        }

        return is_string($label) && $this->shouldTranslateLabel
            ? __($label)
            : $label;
    }

    public function isLabelHidden(): bool
    {
        // The chevron of a split button is icon-only; its label is what a
        // screen reader announces.
        return (! $this->isOverflow) || parent::isLabelHidden();
    }

    public function getIcon(): string | BackedEnum | Htmlable
    {
        return $this->getBaseIcon() ?? config(
            $this->isOverflow ? 'splitbutton-for-filament.overflow.icon' : 'splitbutton-for-filament.split.icon',
            $this->isOverflow ? 'heroicon-m-ellipsis-vertical' : 'heroicon-m-chevron-down',
        );
    }

    /**
     * The chevron takes the colour, size and outline of the button it is
     * joined to, unless set on the split button itself.
     *
     * @return string | array<string> | null
     */
    public function getColor(): string | array | null
    {
        return parent::getColor() ?? ($this->isOverflow
            ? config('splitbutton-for-filament.overflow.color', 'gray')
            : ($this->face()?->getColor() ?? 'primary'));
    }

    public function getSize(): Size | string | null
    {
        return parent::getSize() ?? ($this->isOverflow ? null : $this->face()?->getSize());
    }

    public function isOutlined(): bool
    {
        return parent::isOutlined() || ((! $this->isOverflow) && ($this->face()?->isOutlined() ?? false));
    }

    protected function face(): ?Action
    {
        return $this->composition['faces'][0] ?? null;
    }

    protected function resolveLivewire(): ?object
    {
        try {
            return $this->getLivewire();
        } catch (Throwable) {
            return null;
        }
    }

    protected function manager(): SplitButtonManager
    {
        return app(SplitButtonManager::class);
    }
}
