# splitbutton

<p class="filament-hidden">The button that adapts to how you work.</p>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blemli/splitbutton-for-filament.svg?style=flat-square)](https://packagist.org/packages/blemli/splitbutton-for-filament) [![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/blemli/splitbutton-for-filament/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/blemli/splitbutton-for-filament/actions?query=workflow%3Atests+branch%3Amain) [![Total Downloads](https://img.shields.io/packagist/dt/blemli/splitbutton-for-filament.svg?style=flat-square)](https://packagist.org/packages/blemli/splitbutton-for-filament)

Lets your Action Buttons have Options and lets your users customize those Options.

<img class="filament-hidden" src="art/banner.jpg" alt="splitbutton: a button joined to its menu, and the menu each user arranges" />

> [!IMPORTANT]
>
> PRs, Issues and Agents are welcome 🤗. Let's make this package very nice together 



## Features

users rearrange the menu by long press · drag to reorder · hide entries · pin any entry onto the button · last used moves onto the button · one-tap reset · same menu on every device with `->persist()` · entries from a closure, e.g. your 3 most used templates · compatible with [formsettings](https://github.com/blemli/formsettings-for-filament): quick create / quick edit presets as entries ·  works with [filament-mouseless](https://github.com/blemli/filament-mouseless) · overflow mode: a few buttons + "More" · menu sections · every entry a real Filament action (modals, forms, URLs, key bindings) · page headers & table rows · chevron takes colour, size & outline of its button · trigger at start or end · collapses to a plain button when there is nothing to choose · optional "Customize" hint entry · adjustable long-press time · `->split()` & `->overflow()` macros · everything opt-in · panel-wide defaults, per-button overrides · no script loaded for static buttons · stateless mode without migrations · supports filament-shield permissions · Filament Native Style · dark mode · translated to en, de · Filament 4 & 5  · no extra dependencies · headless install with `--no-interaction` · uninstall with `splitbutton:uninstall`

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

## License

[Unlicense](LICENSE.md): public domain, do what you like.
