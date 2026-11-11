# splitbutton

<p class="filament-hidden">The button that adapts to how you work.</p>

<p class="filament-hidden"><a href="https://packagist.org/packages/blemli/splitbutton-for-filament"><img src="https://img.shields.io/packagist/v/blemli/splitbutton-for-filament.svg?style=flat-square" alt="Latest Version on Packagist" /></a> <a href="https://github.com/blemli/splitbutton-for-filament/actions?query=workflow%3Atests+branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/blemli/splitbutton-for-filament/tests.yml?branch=main&label=tests&style=flat-square" alt="GitHub Tests Action Status" /></a> <a href="https://packagist.org/packages/blemli/splitbutton-for-filament"><img src="https://img.shields.io/packagist/dt/blemli/splitbutton-for-filament.svg?style=flat-square" alt="Total Downloads" /></a></p>

Lets your Action Buttons have Options and lets your users customize those Options.

<img class="filament-hidden" src="art/banner.jpg" alt="splitbutton: a button joined to its menu, and the menu each user arranges" />

<div class="filament-hidden">

> **PRs, Issues and Agents are welcome 🤗.** Let's make this package very nice together

</div>

## Features

users rearrange the menu by long press · drag to reorder · hide entries · pin any entry onto the button or last used moves onto the button · one-tap reset · share customizations across devices · entries from a closure, e.g. your 3 most used templates · use [formsettings](https://github.com/blemli/formsettings-for-filament) quick create / quick edit presets ·  works with [filament-mouseless](https://github.com/blemli/filament-mouseless) · overflow mode: a few buttons + "More" · menu sections · every entry is a real Filament action · use everywhere you can use a normal button · completely respects your app style · trigger at start or end · collapses to a plain button when there is nothing to choose · optional "Customize" hint entry · adjustable long-press time · `->split()` & `->overflow()` macros · everything opt-in · panel-wide defaults, per-button overrides · no script loaded for static buttons · stateless mode without migrations · supports filament-shield permissions · Mobile Friendly · Filament Native Style · dark mode · translated to en, de · Filament 4 & 5  · RTL · no dependencies · headless install with `--no-interaction` · uninstall with `splitbutton:uninstall`

## Installation

```bash
composer require blemli/splitbutton-for-filament
php artisan splitbutton-for-filament:install 
```

## Usage

```php
SplitButton::make([CreateAction::make(), Action::make('import')])   // first action = the button, the rest = its menu
    ->options(fn () => Template::mostUsed(3)->map(fn ($t) => Action::make("template-{$t->id}")->label($t->name)->url(...)))   // … entries from a closure
    ->editable()            // … users rearrange it: long press the menu (or E) to reorder, hide, pick the default, reset
    ->editHint()            // … a visible "Customize" entry for those who never long press
    ->rememberLastUsed()    // … offers users a "last used on the button" toggle
    ->formSettingsPresets() // … lists formsettings-for-filament presets marked quick create / quick edit
    ->overflow(2)           // … instead: two buttons out front, the rest under "More" (->dropdown(false) groups become sections)
    ->triggerPosition('start')   // … menu trigger first
    ->filterUnauthorized();      // … drop unauthorized entries instead of disabling them

// Everything is opt-in. Panel-wide defaults, and what the per-user features need:
$panel->plugin(SplitButtonPlugin::make()->editable()->rememberLastUsed()->persist());   // persist() = database instead of session
```

Also `CreateAction::make()->split([...])` and `ActionGroup::make([...])->split()` / `->overflow(2)`. Keyboard throughout (`E` edit, `Ctrl ↑↓` move, `H` hide, `D` default), works with [filament-mouseless](https://github.com/blemli/filament-mouseless). English and German, light and dark, Filament 4 and 5.

## Limits

- **Where it goes**: wherever Filament takes an action *group*. Tested: page headers and table rows. Should work, not yet tested: table header, toolbar and empty-state actions, form actions, `Actions::make()` in forms and infolists, modal footers.
- **Not in field actions** (`->suffixAction()`, `->hintAction()`, …): Filament takes single actions there.
- **Not in notifications**: Filament rebuilds their actions from arrays, so a split button arrives as a plain dropdown.
- **Not as a method action** on a custom Livewire component (`public function deleteAction(): Action`): Filament does not resolve groups that way.
- **Tables**: `->options(fn)` runs once for the whole table, without a record. For per-row differences use `->visible(fn ($record) => …)` on the entry.
- **Per-user features** (edit mode, pin, last used) need the plugin on the panel and a signed-in user. Anywhere else the button is static.
- **Layouts are per button, not per record**, and gone after logout unless you `->persist()`. Two split buttons on one page that start with the same action need `->key()`.
- **Entries are remembered by action name**: rename an action and users lose its place. Give closure entries stable names (`template-{id}`).
- **Only real actions can sit on the button**, not a submenu.
- **formsettings presets** need formsettings-for-filament 0.5+ with `->presets()`. Edit presets need a record, and create and edit forms keep separate presets unless formsettings runs `->perResource()`.
- **Touch long press and RTL** are built in but not yet tested on real devices.

## License

[Unlicense](LICENSE.md): public domain, do what you like.
