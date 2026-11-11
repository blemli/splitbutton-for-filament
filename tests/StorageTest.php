<?php

use Blemli\SplitButton\Layout;
use Blemli\SplitButton\Models\SplitButtonLayout;
use Blemli\SplitButton\SplitButtonManager;
use Blemli\SplitButton\Storage\DatabaseStore;
use Blemli\SplitButton\Storage\SessionStore;
use Blemli\SplitButton\Tests\Fixtures\User;

it('keeps layouts in the session by default', function () {
    $this->signIn();

    expect(app(SplitButtonManager::class)->store())->toBeInstanceOf(SessionStore::class);

    $store = new SessionStore;
    $store->put('admin::Page::create', new Layout(order: ['b', 'a'], default: 'b'));

    expect($store->get('admin::Page::create')->order)->toBe(['b', 'a'])
        ->and($store->get('admin::Page::create')->default)->toBe('b')
        ->and($store->get('admin::Other::create')->isEmpty())->toBeTrue()
        ->and(SplitButtonLayout::query()->count())->toBe(0);

    $store->forget('admin::Page::create');

    expect($store->get('admin::Page::create')->isEmpty())->toBeTrue();
});

it('keeps layouts per user in the database with persist()', function () {
    $ada = $this->signIn();
    $this->plugin()->persist();

    expect(app(SplitButtonManager::class)->store())->toBeInstanceOf(DatabaseStore::class);

    $store = new DatabaseStore;
    $store->put('admin::Page::create', new Layout(hidden: ['import']));
    $store->put('admin::Page::create', new Layout(hidden: ['import', 'export']));

    expect(SplitButtonLayout::query()->count())->toBe(1)
        ->and($store->get('admin::Page::create')->hidden)->toBe(['import', 'export']);

    $this->signIn(User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']));

    expect($store->get('admin::Page::create')->isEmpty())->toBeTrue();

    $store->put('admin::Page::create', new Layout(default: 'export'));

    expect(SplitButtonLayout::query()->count())->toBe(2);

    $store->forget('admin::Page::create');

    $this->signIn($ada);

    expect($store->get('admin::Page::create')->hidden)->toBe(['import', 'export']);

    $this->plugin()->persist(false);
});

it('writes nothing to the database without a user', function () {
    $store = new DatabaseStore;
    $store->put('admin::Page::create', new Layout(default: 'export'));

    expect(SplitButtonLayout::query()->count())->toBe(0)
        ->and($store->get('admin::Page::create')->isEmpty())->toBeTrue();
});

it('forgets a layout that became empty instead of storing it', function () {
    $this->signIn();
    $this->plugin()->persist();

    $manager = app(SplitButtonManager::class);
    $manager->save('admin::Page::create', new Layout(default: 'export'));

    expect(SplitButtonLayout::query()->count())->toBe(1);

    $manager->save('admin::Page::create', new Layout);

    expect(SplitButtonLayout::query()->count())->toBe(0);

    $this->plugin()->persist(false);
});
