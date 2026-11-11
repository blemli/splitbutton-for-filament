<?php

namespace Blemli\SplitButton;

use BackedEnum;
use Blemli\SplitButton\Enums\AutoPin;
use Blemli\SplitButton\Enums\ChevronPosition;
use Blemli\SplitButton\Support\FlowBridge;
use Blemli\SplitButton\Support\FormSettingsBridge;
use Blemli\SplitButton\Support\RecordOption;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Size;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
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

    /**
     * How records returned by a source become entries, by source.
     *
     * @var array<int, array{url?: Closure | null, label?: Closure | null, icon?: Closure | null, name?: Closure | null}>
     */
    protected array $recordOptionsUsing = [];

    protected bool $isMaterialized = false;

    /**
     * The table row the per-row entries at the end of the menu were built
     * for, and how many of them there are.
     *
     * @var Model | array<string, mixed> | null
     */
    protected Model | array | null $rowOptionsRecord = null;

    protected int $rowOptionCount = 0;

    protected bool | Closure | null $isEditable = null;

    protected bool | Closure | null $isEditableWithMenuEntry = null;

    protected bool | Closure | null $isEditableWithLongPress = null;

    protected bool | Closure | null $isEditableWithKeyboard = null;

    /** @var AutoPin | string | array<AutoPin | string> | bool | Closure | null */
    protected AutoPin | string | array | bool | Closure | null $autoPin = null;

    protected AutoPin | string | bool | null $autoPinByDefault = null;

    protected bool $isAutoPinOptional = false;

    /** @var array<string, string> */
    protected array $renames = [];

    protected bool | Closure | null $hasFormSettingsPresets = null;

    /** @var class-string | null */
    protected ?string $formSettingsResource = null;

    protected ?string $formSettingsOperation = null;

    /** @var bool | array<int | string> | Closure */
    protected bool | array | Closure $workflows = false;

    protected bool $confirmsWorkflows = true;

    protected ?string $stateKey = null;

    protected ?string $declaredName = null;

    protected bool $isOverflow = false;

    protected int | Closure | null $primaryCount = null;

    protected ChevronPosition | string | null $chevronPosition = null;

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
     * More menu entries: actions, or a closure returning them (evaluated
     * per request with $livewire, $record, … injected).
     *
     * Records work as well — models, a collection or a query, straight or
     * from the closure — and become entries by convention: named after
     * model and key ("preset-7"), labelled with the record's title, with
     * the icon of its resource. Behind a CreateAction such an entry opens
     * the page's create form with the record in the query (?preset=7);
     * anywhere else it opens the record. url:, label:, icon: and name:
     * closures (each given the record) replace a convention; return
     * actions instead of records to decide everything yourself.
     *
     *     ->options(Template::query()->latest()->limit(3))
     *
     * In a table a closure runs once for the whole table, before any row.
     * One that asks for the row (a $record parameter, or one typed as a
     * model) runs once per row instead, and may only return links
     * (->url()) or records: Filament finds a clicked row action by name
     * before it knows the row, so an entry that exists for some rows only
     * could never be run. Entries that run code or open a modal are
     * declared up front and switched per row with ->visible(fn ($record) => …).
     *
     * @param  array<Action | ActionGroup | Model> | Closure | Builder | Traversable<mixed>  $options
     */
    public function options(array | Closure | Builder | Traversable $options, ?Closure $url = null, ?Closure $label = null, ?Closure $icon = null, ?Closure $name = null): static
    {
        $isPlainActions = is_array($options) && (array_filter(
            $options,
            fn (mixed $option): bool => ! (($option instanceof Action) || ($option instanceof ActionGroup)),
        ) === []);

        if ($isPlainActions) {
            return $this->actions([...$this->actions, ...$options]);
        }

        // Anything else is looked at once the button is on its page.
        $source = $options instanceof Closure ? $options : fn (): mixed => $options;

        $this->optionSources[] = $source;
        $this->recordOptionsUsing[spl_object_id($source)] = ['url' => $url, 'label' => $label, 'icon' => $icon, 'name' => $name];
        $this->isMaterialized = false;
        $this->rowOptionsRecord = null;

        return $this;
    }

    /**
     * The action the developer declared first, whatever sits on the button
     * for this user.
     */
    public function getDeclaredAction(): ?Action
    {
        return parent::getFlatActions()[$this->declaredName ?? ''] ?? null;
    }

    /**
     * Let users rearrange this menu: its "Customize" entry (or E) enters
     * edit mode to reorder, hide, pick the default and reset. Needs the
     * plugin on the panel.
     */
    public function editable(bool | Closure $condition = true): static
    {
        $this->isEditable = $condition;

        return $this;
    }

    /**
     * The visible "Customize" entry at the end of the editable menu: on
     * unless you pass false — then users need another way into edit mode
     * (->editableWithLongPress(), or E).
     */
    public function editableWithMenuEntry(bool | Closure $condition = true): static
    {
        $this->isEditableWithMenuEntry = $condition;

        return $this;
    }

    /**
     * Also enter edit mode by holding the menu or one of its entries. Off
     * unless you ask for it.
     */
    public function editableWithLongPress(bool | Closure $condition = true): static
    {
        $this->isEditableWithLongPress = $condition;

        return $this;
    }

    /**
     * The button follows the user's picks: what they picked last (Recent),
     * most often (Frequent) or most often lately (Frecent, the default)
     * sits on it. A pin they set by hand in edit mode still wins; taking
     * it off hands the button back to the strategy. Pass false to switch
     * off what the plugin sets panel-wide. Needs the plugin on the panel.
     */
    public function autoPin(AutoPin | string | bool | Closure $strategy = AutoPin::Frecent): static
    {
        $this->autoPin = $strategy;
        $this->isAutoPinOptional = false;
        $this->autoPinByDefault = null;
        $this->composition = null;

        return $this;
    }

    /**
     * Leave it to the user instead: in edit mode they get a switch that
     * makes the button follow their picks — one per strategy when you
     * offer several. Nothing changes for them until they turn one on,
     * unless byDefault says so: true for the first strategy, or the
     * strategy itself.
     *
     * @param  AutoPin | string | array<AutoPin | string> | bool | Closure  $strategies
     */
    public function offerAutoPin(AutoPin | string | array | bool | Closure $strategies = AutoPin::Frecent, AutoPin | string | bool | null $byDefault = null): static
    {
        $this->autoPin = $strategies;
        $this->isAutoPinOptional = true;
        $this->autoPinByDefault = $byDefault;
        $this->composition = null;

        return $this;
    }

    /**
     * Tell the button that actions were renamed (old name => new name).
     * A user's layout knows its entries by action name: without this, a
     * renamed entry is a new one to them, back at the end of the menu,
     * visible again and off the button.
     *
     * @param  array<string, string>  $renames
     */
    public function renamed(array $renames): static
    {
        $this->renames = [...$this->renames, ...$renames];
        $this->composition = null;

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
     * List the packstub/filament-flow workflows a user may start by hand,
     * one entry each: active workflows whose Manual trigger takes the
     * record of this page or table row — or, on a page without a record,
     * the ones that ask for none. Pass ids to list only those. Each asks
     * before it runs unless confirm is false. Inert when that package is
     * not installed.
     *
     * @param  bool | array<int | string> | Closure  $workflows
     */
    public function workflows(bool | array | Closure $workflows = true, bool $confirm = true): static
    {
        $this->workflows = $workflows;
        $this->confirmsWorkflows = $confirm;
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
     * Put the chevron before or after the button(s). Logical: Start is
     * the reading start, so it flips under RTL on its own.
     */
    public function chevronPosition(ChevronPosition | string | null $position): static
    {
        $this->chevronPosition = $position;

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

    public function getChevronPosition(): ChevronPosition
    {
        $position = $this->chevronPosition ?? config('splitbutton-for-filament.chevron_position', 'end');

        return $position instanceof ChevronPosition
            ? $position
            : (ChevronPosition::tryFrom((string) $position) ?? ChevronPosition::End);
    }

    /**
     * The menu opens under the control, along the edge its chevron is on:
     * bottom-end with the chevron at the end, bottom-start with it at the
     * start (both follow the reading direction). Answering here, rather
     * than in the view, keeps Filament from forcing its own page-header
     * default onto a chevron that sits at the start. An explicit
     * ->dropdownPlacement() always wins.
     */
    public function getDropdownPlacement(): ?string
    {
        $explicit = $this->evaluate($this->dropdownPlacement);

        if (filled($explicit)) {
            return $explicit;
        }

        $own = $this->getChevronPosition() === ChevronPosition::Start ? 'bottom-start' : 'bottom-end';

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

    public function isEditableWithMenuEntry(): bool
    {
        return (bool) ($this->evaluate($this->isEditableWithMenuEntry) ?? $this->manager()->plugin()?->isEditableWithMenuEntry() ?? true);
    }

    /**
     * The keyboard shortcuts of edit mode (E, Ctrl / ⌘ with the arrows, H,
     * D): on unless you pass false. Arrows, Enter and Escape keep working.
     */
    public function editableWithKeyboard(bool | Closure $condition = true): static
    {
        $this->isEditableWithKeyboard = $condition;

        return $this;
    }

    public function isEditableWithKeyboard(): bool
    {
        return (bool) ($this->evaluate($this->isEditableWithKeyboard) ?? $this->manager()->plugin()?->isEditableWithKeyboard() ?? true);
    }

    public function isEditableWithLongPress(): bool
    {
        return (bool) ($this->evaluate($this->isEditableWithLongPress) ?? $this->manager()->plugin()?->isEditableWithLongPress() ?? false);
    }

    /**
     * The strategies in play, in the order they were given: the one the
     * button follows, or the ones the user may switch between.
     *
     * @return array<AutoPin>
     */
    public function getAutoPinStrategies(): array
    {
        $strategies = $this->evaluate($this->autoPin);

        if ($strategies === null) {
            return $this->manager()->plugin()?->getAutoPinStrategies() ?? [];
        }

        $strategies = AutoPin::list($strategies);

        // Only a choice can have several.
        return $this->isAutoPinOptional ? $strategies : array_slice($strategies, 0, 1);
    }

    public function hasAutoPin(): bool
    {
        return $this->getAutoPinStrategies() !== [];
    }

    /**
     * Whether the user decides (a switch in edit mode) or the developer did.
     */
    public function isAutoPinOptional(): bool
    {
        return $this->evaluate($this->autoPin) === null
            ? (bool) $this->manager()->plugin()?->isAutoPinOptional()
            : $this->isAutoPinOptional;
    }

    /**
     * The strategy that is on for a user who never touched the switch.
     */
    public function getDefaultAutoPin(): ?AutoPin
    {
        return AutoPin::initial(
            $this->evaluate($this->autoPin) === null
                ? $this->manager()->plugin()?->getAutoPinByDefault()
                : $this->autoPinByDefault,
            $this->getAutoPinStrategies(),
        );
    }

    /**
     * The strategy this user's button follows right now, if any.
     */
    public function getActiveAutoPin(?Layout $layout): ?AutoPin
    {
        $strategies = $this->getAutoPinStrategies();

        if ($strategies === []) {
            return null;
        }

        if (! $this->isAutoPinOptional()) {
            return $strategies[0];
        }

        if ($layout?->autoPin === Layout::OFF) {
            return null;
        }

        $chosen = AutoPin::tryFrom($layout->autoPin ?? '');

        return in_array($chosen, $strategies, true) ? $chosen : $this->getDefaultAutoPin();
    }

    /**
     * @return array<string, string>
     */
    public function getRenames(): array
    {
        return $this->renames;
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
        return ($this->isEditable() || $this->hasAutoPin())
            && $this->manager()->canCustomize()
            && ($this->getStateKey() !== null);
    }

    public function getLayout(): ?Layout
    {
        if (! $this->isStateful()) {
            return null;
        }

        $key = (string) $this->getStateKey();
        $layout = $this->manager()->layout($key);

        if ($this->renames === []) {
            return $layout;
        }

        $renamed = $layout->renamed($this->renames);

        // Stored once under the new names, so the map can go again some day.
        if ($renamed !== $layout) {
            $this->manager()->save($key, $renamed);
        }

        return $renamed;
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
        if ($this->resolveLivewire() === null) {
            return;
        }

        if (! $this->isMaterialized) {
            $this->isMaterialized = true;

            $extra = $this->resolveSources(array_filter(
                $this->optionSources,
                fn (Closure $source): bool => ! $this->isRowSource($source),
            ));

            if ($this->hasFormSettingsPresets()) {
                $extra = [...$extra, ...FormSettingsBridge::actions($this)];
            }

            $workflows = $this->evaluate($this->workflows);

            if (($workflows !== false) && ($workflows !== null)) {
                $extra = [...$extra, ...FlowBridge::actions($this, is_array($workflows) ? $workflows : null, $this->confirmsWorkflows)];
            }

            if ($extra !== []) {
                parent::actions([...$this->actions, ...$this->withoutKnown($extra)]);
            }
        }

        $this->materializeRowOptions();
    }

    /**
     * The entries of closures that ask for the table row: built again for
     * every row, and kept at the end of the menu so they can be taken off
     * when the button moves on to the next row.
     */
    protected function materializeRowOptions(): void
    {
        $sources = array_filter($this->optionSources, $this->isRowSource(...));

        if ($sources === []) {
            return;
        }

        $record = $this->getRecord(withDefault: false);

        if ($record === $this->rowOptionsRecord) {
            return;
        }

        if ($this->rowOptionCount > 0) {
            parent::actions(array_slice($this->actions, 0, -$this->rowOptionCount));
        }

        $this->rowOptionsRecord = $record;
        $this->rowOptionCount = 0;
        $this->composition = null;

        if ($record === null) {
            return;
        }

        $extra = array_values($this->withoutKnown($this->resolveSources($sources)));

        if ($extra === []) {
            return;
        }

        parent::actions([...$this->actions, ...$extra]);
        $this->rowOptionCount = count($extra);

        foreach ($extra as $entry) {
            foreach ($entry instanceof Action ? [$entry] : $entry->getFlatActions() as $action) {
                if (str_starts_with((string) $action->getLivewireClickHandler(), 'mountAction(')) {
                    throw new LogicException(
                        "Split button option [{$action->getName()}] comes from a per-row closure and is not a link. "
                        . 'Filament finds a clicked row action by name before it knows the row, so it could never be run. '
                        . 'Give it a ->url(), or declare it up front and switch it per row with ->visible(fn ($record) => …).',
                    );
                }
            }
        }
    }

    /**
     * In a table, a closure that asks for the record means the row.
     */
    protected function isRowSource(Closure $source): bool
    {
        if ($this->getTable() === null) {
            return false;
        }

        foreach ((new ReflectionFunction($source))->getParameters() as $parameter) {
            if ($parameter->getName() === 'record') {
                return true;
            }

            $type = $parameter->getType();

            if (($type instanceof ReflectionNamedType) && (! $type->isBuiltin()) && is_a($type->getName(), Model::class, allow_string: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Closure>  $sources
     * @return array<Action | ActionGroup>
     */
    protected function resolveSources(array $sources): array
    {
        $resolved = [];

        foreach ($sources as $source) {
            $actions = $this->evaluate($source);

            if ($actions instanceof Builder) {
                $actions = $actions->get();
            }

            if ($actions instanceof Traversable) {
                $actions = iterator_to_array($actions, preserve_keys: false);
            }

            foreach (($actions instanceof Model) ? [$actions] : (array) $actions as $action) {
                if (($action instanceof Action) || ($action instanceof ActionGroup)) {
                    $resolved[] = $action;
                } elseif ($action instanceof Model) {
                    $resolved[] = RecordOption::make($this, $action, $this->recordOptionsUsing[spl_object_id($source)] ?? []);
                }
            }
        }

        return $resolved;
    }

    /**
     * A closure must never replace an entry that is already there.
     *
     * @param  array<Action | ActionGroup>  $extra
     * @return array<Action | ActionGroup>
     */
    protected function withoutKnown(array $extra): array
    {
        $known = parent::getFlatActions();

        return array_filter(
            $extra,
            fn (Action | ActionGroup $action): bool => ! (($action instanceof Action) && array_key_exists($action->getName(), $known)),
        );
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
     *     picks: array<string, string | null>,
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
        $picks = [];

        if ($layout !== null) {
            $now = now()->getTimestamp();
            $halfLife = $this->manager()->halfLife();

            foreach ($this->getAutoPinStrategies() as $strategy) {
                $picks[$strategy->value] = $layout->pick($strategy, array_map(strval(...), array_keys($candidates)), $now, $halfLife);
            }

            // A pin the user set by hand is the last word; without one the
            // button follows their picks.
            $pinned = isset($candidates[$layout->default ?? ''])
                ? $layout->default
                : ($picks[$this->getActiveAutoPin($layout)?->value] ?? null);
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
            'picks' => $picks,
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
        $attributes = ['data-splitbutton-option' => e($name)];

        // The declared create / edit / view action says so, wherever the
        // user's layout put it: a keyboard shortcut for "new" must mean the
        // same thing every day, whatever sits on the button. This is the
        // attribute blemli/filament-mouseless looks for.
        if (($name === $this->declaredName) && (($shortcut = $this->shortcutOf($action)) !== null)) {
            $attributes['data-mouseless'] = $shortcut;
        }

        $action->extraAttributes($attributes, merge: true);
    }

    protected function shortcutOf(Action $action): ?string
    {
        return match (true) {
            $action instanceof CreateAction => 'crud.create',
            $action instanceof EditAction => 'crud.edit',
            $action instanceof ViewAction => 'crud.view',
            default => null,
        };
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
            // Every strategy in play, with the entry it would put on the button.
            'strategies' => array_map(
                fn (AutoPin $strategy): array => [
                    'name' => $strategy->value,
                    'label' => __("splitbutton-for-filament::splitbutton.pin_{$strategy->value}"),
                    'pick' => $composition['picks'][$strategy->value] ?? null,
                ],
                $this->getAutoPinStrategies(),
            ),
            'strategy' => $this->getActiveAutoPin($layout)?->value,
            // A switch per strategy in edit mode, or none: the developer decided.
            'canSwitchStrategy' => $this->hasAutoPin() && $this->isAutoPinOptional(),
            // Count picks whenever the button may follow them.
            'track' => $this->hasAutoPin(),
            'longPress' => $this->isEditable() && $this->isEditableWithLongPress(),
            'keyboard' => $this->isEditable() && $this->isEditableWithKeyboard(),
            'longPressDelay' => $manager->plugin()?->getLongPressDelay() ?? 500,
            'urls' => [
                'layout' => $manager->url('layout'),
                'used' => $manager->url('used'),
            ],
        ];
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
