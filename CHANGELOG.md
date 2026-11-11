# Changelog

All notable changes to `splitbutton-for-filament` will be documented in this file.

## v0.1.0 - 2026-11-11

- `SplitButton`: an `ActionGroup` drawn as one button joined to a menu of its variants; the first action is the button
- `->options()`: more entries from an array or a closure (evaluated per request with `$livewire` injected)
- `->overflow()`: a row of buttons with the rest under "More"; sections from `->dropdown(false)` groups, `->triggerPosition()`, `->filterUnauthorized()`, config defaults
- `->editable()`: per-user edit mode (long press or `E`) to reorder by drag or `Ctrl ↑↓`, hide, pin to the button, reset; `->editHint()` adds a visible entry
- `->rememberLastUsed()`: a per-user "last used on the button" toggle
- `->formSettingsPresets()`: lists blemli/formsettings-for-filament presets marked quick create / quick edit (soft integration, neither package requires the other)
- `SplitButtonPlugin`: panel-wide defaults for all of the above, `->persist()` (database instead of session), `->authorize()`, `->longPress()`
- `Action::split()`, `ActionGroup::split()` and `ActionGroup::overflow()` macros
- `InteractsWithSplitButtonActions` trait: split buttons on your own Livewire components (`getSplitButtons()`, `getSplitButton('name')`)
- Works wherever Filament takes an action group: page headers, table header / toolbar / empty-state / row actions, `Actions` blocks in forms and infolists, section headers, modal footers
- Install and uninstall commands, non-interactive with flags
- English and German, light and dark, Filament 4 and 5

Browser QA (Playwright, Chromium, on a Filament 5.11 host app, light and dark): open/close, keyboard edit mode, long press on the trigger and on an entry, drag reorder, hide (last entry refused), pin, reset, last used after navigating, reload persistence with `->persist()`, overflow row with sections and its edit mode, trigger-first and outlined joins, filament-mouseless jump mode, formsettings quick preset entry, right-to-left (Arabic locale: mirrored joins, menus, edit mode, drag and keyboard); console and network clean. Also clicked through: a split button on a custom component, in a section header, a form `Actions` block, table header, toolbar and rows, and a modal footer (menu on top, entries and closure entries run, edit mode). Table empty-state actions, infolist `Actions` blocks and Filament 4 are covered by the test suite only.
