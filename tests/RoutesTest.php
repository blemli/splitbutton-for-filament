<?php

use Blemli\SplitButton\Enums\Customization;
use Blemli\SplitButton\Events\SplitButtonCustomized;
use Blemli\SplitButton\Events\SplitButtonReset;
use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButtonManager;
use Illuminate\Support\Facades\Event;

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
        'auto_pin' => 'frecent',
    ])->assertOk()->assertJsonPath('layout.default', 'export');

    expect(stored()->order)->toBe(['export', 'create'])
        ->and(stored()->hidden)->toBe(['import'])
        ->and(stored()->default)->toBe('export')
        ->and(stored()->autoPin)->toBe('frecent');

    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'auto_pin' => 'off'])->assertOk();

    expect(stored()->autoPin)->toBe('off');

    // A button without a switch says nothing about it: the choice stays.
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'order' => ['a'], 'auto_pin' => null])->assertOk();

    expect(stored()->autoPin)->toBe('off');
});

it('counts every pick, and keeps the counts when the layout is saved', function () {
    $this->signIn();

    $this->travelTo('2026-01-01 12:00:00');
    $this->post('/admin/splitbutton/used', ['key' => KEY, 'name' => 'export'])->assertNoContent();
    $this->post('/admin/splitbutton/used', ['key' => KEY, 'name' => 'export'])->assertNoContent();
    $this->post('/admin/splitbutton/used', ['key' => KEY, 'name' => 'import'])->assertNoContent();

    expect(stored()->usage['export']['count'])->toBe(2)
        ->and(stored()->usage['export']['score'])->toBe(2.0)
        ->and(stored()->usage['import']['count'])->toBe(1)
        ->and(stored()->lastUsed)->toBe('import');

    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'order' => ['import', 'export'], 'usage' => ['hack' => ['count' => 99]]])->assertOk();

    expect(array_keys(stored()->usage))->toBe(['export', 'import']);
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

it('tells the application what a user did to a menu', function () {
    $user = $this->signIn();
    Event::fake([SplitButtonCustomized::class, SplitButtonReset::class]);

    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'order' => ['create', 'export'], 'default' => 'export', 'change' => 'pinned', 'entry' => 'export'])->assertOk();

    Event::assertDispatched(SplitButtonCustomized::class, fn (SplitButtonCustomized $event): bool => $event->user->is($user)
        && $event->key === KEY
        && $event->panel() === 'admin'
        && $event->component() === 'App\\Filament\\Pages\\ListShoots'
        && $event->button() === 'create'
        && $event->is(Customization::Pinned)
        && $event->entry === 'export'
        && $event->layout->default === 'export'
        && $event->previous->isEmpty());

    // The next change comes with the layout as it was before.
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'order' => ['create', 'export'], 'hidden' => ['export'], 'change' => 'hidden', 'entry' => 'export'])->assertOk();

    Event::assertDispatched(SplitButtonCustomized::class, fn (SplitButtonCustomized $event): bool => $event->is(Customization::Hidden)
        && $event->previous->default === 'export'
        && $event->layout->hidden === ['export']);

    // Without the hint it still fires; a made-up one is refused.
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'order' => ['export', 'create']])->assertOk();
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'change' => 'exploded'])->assertStatus(422);

    Event::assertDispatchedTimes(SplitButtonCustomized::class, 3);
    Event::assertDispatched(SplitButtonCustomized::class, fn (SplitButtonCustomized $event): bool => $event->change === null);

    // A pick is not a customization.
    $this->post('/admin/splitbutton/used', ['key' => KEY, 'name' => 'export'])->assertNoContent();

    Event::assertDispatchedTimes(SplitButtonCustomized::class, 3);
    Event::assertNotDispatched(SplitButtonReset::class);

    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'reset' => true])->assertOk();

    Event::assertDispatched(SplitButtonReset::class, fn (SplitButtonReset $event): bool => $event->user->is($user)
        && $event->key === KEY
        && $event->previous->order === ['export', 'create']);
});

it('fires nothing for those who may not customize', function () {
    $this->signIn();
    $this->plugin()->authorize(false);
    Event::fake([SplitButtonCustomized::class, SplitButtonReset::class]);

    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'hidden' => ['import'], 'change' => 'hidden'])->assertForbidden();
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'reset' => true])->assertForbidden();

    Event::assertNothingDispatched();

    $this->plugin()->authorize(true);
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
    $this->postJson('/admin/splitbutton/layout', ['key' => KEY, 'auto_pin' => 'whatever'])->assertStatus(422);
    $this->postJson('/admin/splitbutton/used', ['key' => KEY])->assertStatus(422);
});
