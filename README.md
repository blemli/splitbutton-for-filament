# splitbutton

<p class="filament-hidden">The button that adapts to how you work.</p>

<img class="filament-hidden" src="https://raw.githubusercontent.com/blemli/splitbutton-for-filament/main/art/banner.jpg" alt="splitbutton" />

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blemli/splitbutton-for-filament.svg?style=flat-square)](https://packagist.org/packages/blemli/splitbutton-for-filament) [![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/blemli/splitbutton-for-filament/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/blemli/splitbutton-for-filament/actions?query=workflow%3Atests+branch%3Amain) [![Total Downloads](https://img.shields.io/packagist/dt/blemli/splitbutton-for-filament.svg?style=flat-square)](https://packagist.org/packages/blemli/splitbutton-for-filament)

A Filament action button joined to a menu of its variants. It is an `ActionGroup` of real actions, so it goes wherever Filament takes one, and each user can make the menu their own: reorder, hide, choose what sits on the button. [filament-action-overflow](https://github.com/harvirsidhu/filament-action-overflow) composes a static split or "More" row; this adds the per-user layer and covers that composition too.

![splitbutton](https://raw.githubusercontent.com/blemli/splitbutton-for-filament/main/art/screenshot.png)

## Installation

```bash
composer require blemli/splitbutton-for-filament
php artisan splitbutton-for-filament:install -n   # add --run-migrations for ->persist(); remove with splitbutton:uninstall
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

MIT © [blemli](https://github.com/blemli)
