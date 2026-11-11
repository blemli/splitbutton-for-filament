@php
    use Blemli\SplitButton\Enums\ChevronPosition;
    use Blemli\SplitButton\SplitButtonServiceProvider;
    use Filament\Support\Enums\Width;
    use Filament\Support\Facades\FilamentAsset;

    use function Filament\Support\generate_icon_html;

    /** @var \Blemli\SplitButton\SplitButton $group */
    $composition = $group->compose();
    $faces = $composition['faces'];
    $sections = $composition['sections'];
    $hasTrigger = $composition['hasTrigger'];

    $isOverflow = $group->isOverflow();
    $isStateful = $hasTrigger && $group->isStateful();
    $isEditable = $isStateful && $group->isEditable();
    $hasLongPress = $isEditable && $group->isEditableWithLongPress();
    $hasKeyboard = $isEditable && $group->isEditableWithKeyboard();
    $tooltip = match (true) {
        $hasKeyboard && $hasLongPress => 'customize_tooltip_hold',
        $hasKeyboard => 'customize_tooltip',
        $hasLongPress => 'customize_tooltip_hold_only',
        default => null,
    };
    $chevronFirst = $group->getChevronPosition() === ChevronPosition::Start;

    $maxHeight = $group->getDropdownMaxHeight();
    $width = $group->getDropdownWidth();

    // ->dropdownWidth('xs') and ->dropdownWidth(Width::ExtraSmall) are the same wish.
    if (is_string($width)) {
        $width = Width::tryFrom($width) ?? $width;
    }
    $placement = $group->getDropdownPlacement();
    $lang = 'splitbutton-for-filament::splitbutton.';
@endphp

@if ($hasTrigger)
    <div
        @if ($isStateful)
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('splitbutton', SplitButtonServiceProvider::ASSET_PACKAGE) }}"
            x-data="splitButton()"
            x-on:click.capture="onFaceClick($event)"
            data-splitbutton="{{ json_encode($group->getClientState()) }}"
        @endif
        @class([
            'fi-splitbutton',
            'fi-splitbutton-split' => ! $isOverflow,
            'fi-splitbutton-overflow' => $isOverflow,
            'fi-splitbutton-chevron-start' => $chevronFirst,
            'fi-splitbutton-editable' => $isEditable,
            'fi-splitbutton-longpress' => $hasLongPress,
        ])
    >
        @foreach ($faces as $face)
            {!! $group->renderFace($face) !!}
        @endforeach

        <div
            x-data="filamentDropdown"
            {{ $group->getExtraDropdownAttributeBag()->class(['fi-dropdown fi-splitbutton-dropdown']) }}
        >
            <div
                @if ($isStateful)
                    x-on:mousedown="if ($event.button === 0 && ! sb.suppressClick) toggle($event)"
                    x-bind="sb.surface"
                @else
                    x-on:mousedown="if ($event.button === 0) toggle($event)"
                @endif
                {{-- Filament 4's dropdown has no keyboard layer of its own. --}}
                x-on:keyup.enter="typeof handleTriggerKeydown === 'undefined' && toggle($event)"
                x-on:keyup.space="typeof handleTriggerKeydown === 'undefined' && toggle($event)"
                @if ($tooltip === 'customize_tooltip_hold')
                    title="{{ __($lang . 'customize_tooltip_hold') }}"
                @elseif ($tooltip === 'customize_tooltip')
                    title="{{ __($lang . 'customize_tooltip') }}"
                @elseif ($tooltip === 'customize_tooltip_hold_only')
                    title="{{ __($lang . 'customize_tooltip_hold_only') }}"
                @endif
                @if ($hasKeyboard)
                    aria-keyshortcuts="E"
                @endif
                class="fi-dropdown-trigger"
            >
                {!! $group->toTriggerHtml() !!}
            </div>

            <div
                x-cloak
                x-float.placement.{{ $placement }}{{ $group->hasDropdownFlip() ? '.flip' : '' }}{{ $group->hasDropdownTeleport() ? '.teleport' : '' }}.offset="{ offset: {{ $group->getDropdownOffset() ?? 8 }} }"
                x-ref="panel"
                x-transition:enter-start="fi-opacity-0"
                x-transition:leave-end="fi-opacity-0"
                role="menu"
                @if ($isStateful)
                    x-bind="sb.surface"
                @endif
                @if ($isEditable)
                    {{-- Edit rows carry a handle and two toggles next to the
                         label: unless the developer chose a width, borrow
                         Filament's own (important, layered) width class. --}}
                    x-bind:class="{ 'fi-splitbutton-editing': sb.editing, 'fi-width-xs': sb.editing && @js(blank($width)) }"
                @endif
                @class([
                    'fi-dropdown-panel fi-splitbutton-panel',
                    ($width instanceof Width) ? "fi-width-{$width->value}" : (is_string($width) ? $width : ''),
                    'fi-scrollable' => $maxHeight,
                    'fi-splitbutton-editable' => $isEditable,
                    'fi-splitbutton-longpress' => $hasLongPress,
                ])
                @style([
                    ('max-height: ' . e($maxHeight)) => $maxHeight,
                ])
            >
                @foreach ($sections as $section)
                    <div
                        class="fi-dropdown-list"
                        role="group"
                        @if ($isEditable) x-show="! sb.editing" @endif
                    >
                        @foreach ($section as $entry)
                            {!! $group->renderEntry($entry) !!}
                        @endforeach
                    </div>
                @endforeach

                @if ($isEditable)
                    @if ($group->isEditableWithMenuEntry())
                        <div class="fi-dropdown-list" role="group" x-show="! sb.editing">
                            <button
                                type="button"
                                class="fi-dropdown-list-item fi-splitbutton-hint"
                                x-on:click.stop="sb.startEditing()"
                            >
                                {{ generate_icon_html('heroicon-m-adjustments-horizontal') }}

                                <span class="fi-dropdown-list-item-label">
                                    {{ __($lang . 'customize') }}
                                </span>
                            </button>
                        </div>
                    @endif

                    <template x-if="sb.editing">
                        <div class="fi-dropdown-header fi-splitbutton-edit-header">
                            {{ generate_icon_html('heroicon-m-adjustments-horizontal') }}

                            <span>{{ __($lang . 'editing') }}</span>
                        </div>
                    </template>

                    <template
                        x-for="(section, sectionIndex) in (sb.editing ? sb.sections : [])"
                        x-bind:key="sectionIndex"
                    >
                        <div
                            class="fi-dropdown-list fi-splitbutton-edit-list"
                            role="listbox"
                            aria-label="{{ __($lang . 'editing') }}"
                            x-sortable
                            x-on:end.stop="sb.reorder(sectionIndex, $event.target.sortable.toArray())"
                            data-sortable-animation-duration="150"
                        >
                            <template x-for="item in section" x-bind:key="item.name">
                                <div
                                    class="fi-dropdown-list-item fi-splitbutton-edit-row"
                                    role="option"
                                    tabindex="-1"
                                    x-bind:x-sortable-item="item.name"
                                    x-bind:data-name="item.name"
                                    x-bind:aria-selected="sb.isFace(item.name) || sb.isPinned(item.name)"
                                    x-bind:class="{ 'fi-splitbutton-row-hidden': item.hidden, 'fi-splitbutton-row-face': sb.isFace(item.name) || sb.isPinned(item.name) }"
                                >
                                    <button
                                        type="button"
                                        tabindex="-1"
                                        x-sortable-handle
                                        class="fi-splitbutton-edit-btn fi-splitbutton-handle"
                                        aria-label="{{ __($lang . 'move') }}"
                                        title="{{ __($lang . 'move') }}"
                                    >
                                        {{ generate_icon_html('heroicon-m-bars-2') }}
                                    </button>

                                    <span class="fi-dropdown-list-item-label" x-text="item.label"></span>

                                    <button
                                        type="button"
                                        tabindex="-1"
                                        class="fi-splitbutton-edit-btn fi-splitbutton-pin"
                                        x-show="item.pinnable"
                                        x-on:click.stop="sb.togglePin(item.name)"
                                        x-bind:aria-pressed="sb.isPinned(item.name)"
                                        x-bind:aria-label="sb.isPinned(item.name) ? @js(__($lang . 'unset_default')) : @js(__($lang . 'set_default'))"
                                        x-bind:title="sb.isPinned(item.name) ? @js(__($lang . 'unset_default')) : @js(__($lang . 'set_default'))"
                                    >
                                        <span x-show="! (sb.isFace(item.name) || sb.isPinned(item.name))">
                                            {{ generate_icon_html('heroicon-o-star') }}
                                        </span>

                                        <span x-show="sb.isFace(item.name) || sb.isPinned(item.name)" x-cloak>
                                            {{ generate_icon_html('heroicon-s-star') }}
                                        </span>
                                    </button>

                                    <button
                                        type="button"
                                        tabindex="-1"
                                        class="fi-splitbutton-edit-btn fi-splitbutton-eye"
                                        x-on:click.stop="sb.toggleHidden(item.name)"
                                        x-bind:aria-pressed="item.hidden"
                                        x-bind:aria-label="item.hidden ? @js(__($lang . 'show')) : @js(__($lang . 'hide'))"
                                        x-bind:title="item.hidden ? @js(__($lang . 'show')) : @js(__($lang . 'hide'))"
                                    >
                                        <span x-show="! item.hidden">
                                            {{ generate_icon_html('heroicon-m-eye') }}
                                        </span>

                                        <span x-show="item.hidden" x-cloak>
                                            {{ generate_icon_html('heroicon-m-eye-slash') }}
                                        </span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="sb.editing">
                        <div class="fi-dropdown-list fi-splitbutton-edit-footer" role="group">
                            {{-- One switch per strategy on offer; at most one is on. --}}
                            <template x-for="option in (sb.canSwitchStrategy ? sb.strategies : [])" x-bind:key="option.name">
                                <button
                                    type="button"
                                    class="fi-dropdown-list-item fi-splitbutton-strategy"
                                    x-bind:role="sb.strategies.length > 1 ? 'menuitemradio' : 'menuitemcheckbox'"
                                    x-bind:aria-checked="sb.strategy === option.name"
                                    x-bind:data-strategy="option.name"
                                    x-on:click.stop="sb.toggleStrategy(option.name)"
                                >
                                    <span class="fi-splitbutton-check" x-bind:class="{ 'fi-splitbutton-checked': sb.strategy === option.name }">
                                        {{ generate_icon_html('heroicon-m-check') }}
                                    </span>

                                    <span class="fi-dropdown-list-item-label" x-text="option.label"></span>
                                </button>
                            </template>

                            <button
                                type="button"
                                class="fi-dropdown-list-item"
                                x-on:click.stop="sb.reset()"
                            >
                                {{ generate_icon_html('heroicon-m-arrow-uturn-left') }}

                                <span class="fi-dropdown-list-item-label">
                                    {{ __($lang . 'reset') }}
                                </span>
                            </button>

                            <button
                                type="button"
                                class="fi-dropdown-list-item"
                                x-on:click.stop="sb.finish()"
                            >
                                {{ generate_icon_html('heroicon-m-check') }}

                                <span class="fi-dropdown-list-item-label">
                                    {{ __($lang . 'done') }}
                                </span>
                            </button>

                            @if ($hasKeyboard)
                                {{-- Ctrl + arrows belongs to macOS (Mission Control, Spaces): there it is ⌘. --}}
                                <p
                                    class="fi-splitbutton-keys"
                                    x-text="sb.keys(@js(__($lang . 'keys')), @js(__($lang . 'key_ctrl')))"
                                ></p>
                            @endif
                        </div>
                    </template>
                @endif
            </div>
        </div>
    </div>
@else
    @foreach ($faces as $face)
        {!! $group->renderFace($face) !!}
    @endforeach
@endif
