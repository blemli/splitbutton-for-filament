<?php

use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButtonManager;

const KEY = 'admin::App.Filament.Pages.ListShoots::create';

function stored(): Layout
{
    $manager = app(SplitButtonManager::class);
    $manager->forgetCached();

    return $manager->layout(KEY);
}

it('saves the signed-in user\'s layout', function () {
    $this->signIn();

    $this->postJson('/admin/splitbutton/layout', [
        'key' => KEY,
        'order' => ['export', 'create'],
        'hidden' => ['import'],
        'default' => 'export',
        'remember_last' => true,
    ])->assertOk()->assertJsonPath('layout.default', 'export');

    expect(stored()->order)->toBe(['export', 'create'])
        ->and(stored()->hidden)->toBe(['import'])
        ->and(stored()->default)->toBe('export')
        ->and(stored()->rememberLast)->toBeTrue();
});

it('never leaves a hidden entry pinned to the button', function () {
    $this->signIn();

    $this->postJson('/admin/splitbutton/layout', [
        'key' => KEY,
        'hidden' => ['export'],
        'default' => 'export',
    ])->assertOk();

    expect(stored()->default)->toBeNull();
});

it('records the last used entry without touching the rest', function () {
    $this->signIn();

    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'order' => ['b', 'a']])->assertOk();
    $this->post('/admin/splitbutton/used', ['key' => KEY, 'name' => 'b'])->assertNoContent();

    expect(stored()->lastUsed)->toBe('b')
        ->and(stored()->order)->toBe(['b', 'a']);

    // Saving the layout again keeps what was used last.
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'order' => ['a', 'b']])->assertOk();

    expect(stored()->lastUsed)->toBe('b');
});

it('resets the whole menu', function () {
    $this->signIn();

    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'hidden' => ['import'], 'default' => 'export'])->assertOk();
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'reset' => true])->assertOk();

    expect(stored()->isEmpty())->toBeTrue();
});

it('rejects guests', function () {
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY])->assertUnauthorized();
});

it('rejects users who may not customize', function () {
    $this->signIn();
    $this->plugin()->authorize(false);

    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'hidden' => ['import']])->assertForbidden();

    $this->plugin()->authorize(true);
});

it('rejects keys of another panel and malformed payloads', function () {
    $this->signIn();

    $this->postJson('/admin/splitbutton/layout', ['key' => 'other::Page::create'])->assertStatus(422);
    $this->postJson('/admin/splitbutton/layout', ['order' => ['a']])->assertStatus(422);
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'order' => 'nope'])->assertStatus(422);
    $this->postJson('/admin/splitbutton/used', ['key' => KEY])->assertStatus(422);
});
