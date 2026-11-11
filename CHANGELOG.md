# Changelog

All notable changes to `splitbutton-for-filament` will be documented in this file.

## v0.1.0 - 2026-11-11

- `SplitButton`: an `ActionGroup` drawn as one button joined to a menu of its variants; the first action is the button
- `->options()`: more entries from an array or a closure (evaluated per request with `$livewire` injected), or straight from records (a query, a collection, models): each becomes an entry by convention — `template-7`, the record's title, its resource's icon, and a link to the create form (`?template=7`) behind a `CreateAction` or to the record elsewhere; `url:`, `label:`, `icon:` and `name:` closures replace a convention; in a table, a closure that asks for `$record` runs once per row and returns links
- `->overflow()`: a row of buttons with the rest under "More"; sections from `->dropdown(false)` groups, `->chevronPosition()`, `->filterUnauthorized()`, config defaults
- `->editable()`: per-user edit mode (the menu's "Customize" entry or `E`) to reorder by drag or `Ctrl ↑↓` (`⌘ ↑↓` on a Mac), hide, pin to the button, reset; `->editableWithLongPress()` adds holding the menu as a way in, `->editableWithKeyboard(false)` drops the shortcuts (E, H, D, Ctrl / ⌘ with the arrows), `->editableWithMenuEntry(false)` removes the visible entry; `->editable(fn () => …)` or the plugin's `->authorize('ability')` keep it to some users
- `->autoPin()`: the button follows the user's picks — `AutoPin::Recent` (last used), `Frequent` (most used) or `Frecent` (most used lately, the default); a pin set by hand in edit mode still wins; `->offerAutoPin()` leaves it to each user instead (a switch in edit mode, one per strategy when several are offered, `byDefault:` to start with one on); `SplitButtonPlugin::frecencyHalfLife()` sets how fast old picks fade (14 days)
- Laravel events: `SplitButtonCustomized` (user, button, layout before and after, what was done and to which entry) and `SplitButtonReset`, e.g. for a tutorials or onboarding package
- `->renamed(['old' => 'new'])`: users keep the place, pin and picks of a renamed action
- `->formSettingsPresets()`: lists blemli/formsettings-for-filament presets marked quick create / quick edit (soft integration, neither package requires the other)
- `->workflows()`: lists the packstub/filament-flow workflows that can be started by hand (Manual trigger, matching the page's or row's record) as entries; asks before running unless `confirm: false`; pass ids to list only some (soft integration, ignored when that package is not installed)
- `SplitButtonPlugin`: panel-wide defaults for all of the above, `->persist()` (database instead of session), `->authorize()`, `->longPressDelay()`
- `Action::split()`, `ActionGroup::split()` and `ActionGroup::overflow()` macros
- `InteractsWithSplitButtonActions` trait: split buttons on your own Livewire components (`getSplitButtons()`, `getSplitButton('name')`)
- Works wherever Filament takes an action group: page headers, table header / toolbar / empty-state / row actions, `Actions` blocks in forms and infolists, section headers, modal footers
- Install and uninstall commands, non-interactive with flags; the installer offers to register the plugin in a panel provider (`--panel=admin`), with `->persist()` when the menus go into the database
- The declared create / edit / view action carries `data-mouseless="crud.create"` (…) wherever a user's layout puts it, so filament-mouseless shortcuts keep meaning "new" and "edit"
- English, German, Spanish, French and Italian; light and dark; Filament 4 and 5

Browser QA (Playwright, Chromium, on a Filament 5.11 host app, light and dark): open/close, keyboard edit mode, long press on the trigger and on an entry, drag reorder, hide (last entry refused), pin, reset, last used after navigating, reload persistence with `->persist()`, overflow row with sections and its edit mode, trigger-first and outlined joins, filament-mouseless jump mode, formsettings quick preset entry, right-to-left (Arabic locale: mirrored joins, menus, edit mode, drag and keyboard); console and network clean. Also clicked through: a split button on a custom component, in a section header, a form `Actions` block, table header, toolbar and rows, and a modal footer (menu on top, entries and closure entries run, edit mode). Table empty-state actions, infolist `Actions` blocks and Filament 4 are covered by the test suite only.
