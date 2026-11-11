<?php

use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\SplitButtonManager;
use Blemli\SplitButton\Tests\Fixtures\FakeWorkflow;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Blemli\SplitButton\Tests\Fixtures\User;
use Blemli\SplitButton\Tests\Fixtures\UserResource;
use Blemli\SplitButton\Tests\Fixtures\UserResource\ListUsers;
use Blemli\SplitButton\Tests\Fixtures\UsersTable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
 * ->options() with records instead of actions: each becomes an entry by
 * convention, so a developer does not write name, label, icon and URL out
 * for every one.
 */

function recordEntries(SplitButton $button): array
{
    return array_map(
        fn (Action $action): array => ['name' => $action->getName(), 'label' => $action->getLabel(), 'icon' => $action->getIcon(), 'url' => $action->getUrl()],
        array_values(array_filter($button->getFlatActions(), fn (Action $action): bool => ! in_array($action->getName(), ['create', 'open'], true))),
    );
}

function headerButton(string $page): SplitButton
{
    return collect(Livewire::test($page)->instance()->getCachedHeaderActions())->first();
}

beforeEach(function () {
    $this->ada = $this->signIn();
    $this->bob = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);
});

it('turns the records of a query into "new one from this" entries behind a create button', function () {
    ListUsers::$headerActions = fn (): array => [
        SplitButton::make([CreateAction::make()])->options(User::query()->orderBy('id')),
    ];

    expect(recordEntries(headerButton(ListUsers::class)))->toBe([
        ['name' => "user-{$this->ada->id}", 'label' => 'Ada', 'icon' => 'heroicon-o-user', 'url' => UserResource::getUrl('create', ['user' => $this->ada->id])],
        ['name' => "user-{$this->bob->id}", 'label' => 'Bob', 'icon' => 'heroicon-o-user', 'url' => UserResource::getUrl('create', ['user' => $this->bob->id])],
    ]);

    Livewire::test(ListUsers::class)->assertSee('Bob')->assertSeeHtml("/admin/users/create?user={$this->bob->id}");
});

it('leads to the record itself anywhere else', function () {
    TestPage::$headerActions = fn (): array => [
        SplitButton::make([Action::make('open')->url('/open')])->options(fn () => User::query()->orderBy('id')->get()),
    ];

    $entries = recordEntries(headerButton(TestPage::class));

    expect($entries)->toHaveCount(2)
        ->and($entries[1])->toBe(['name' => "user-{$this->bob->id}", 'label' => 'Bob', 'icon' => 'heroicon-o-user', 'url' => UserResource::getUrl('edit', ['record' => $this->bob])]);
});

it('does not run the query before the button is on its page', function () {
    DB::enableQueryLog();

    $button = SplitButton::make([Action::make('open')->url('/open')])->options(User::query());

    expect(DB::getQueryLog())->toBe([]);

    $button->livewire(new TestPage);

    expect(recordEntries($button))->toHaveCount(2);
});

it('takes a single record, and records mixed with actions', function () {
    TestPage::$headerActions = fn (): array => [
        SplitButton::make([Action::make('open')->url('/open')])
            ->options(fn () => $this->bob)
            ->options([Action::make('extra')->url('/extra'), $this->ada]),
    ];

    expect(array_column(recordEntries(headerButton(TestPage::class)), 'name'))
        ->toBe(["user-{$this->bob->id}", 'extra', "user-{$this->ada->id}"]);
});

it('lets url, label and icon closures replace a convention', function () {
    TestPage::$headerActions = fn (): array => [
        SplitButton::make([Action::make('open')->url('/open')])->options(
            User::query()->whereKey($this->bob->id),
            url: fn (User $user): string => "/profile/{$user->id}",
            label: fn ($record): string => "Profile of {$record->name}",
            icon: fn (): string => 'heroicon-o-star',
            name: fn (User $user): string => "profile-{$user->id}",
        ),
    ];

    expect(recordEntries(headerButton(TestPage::class)))->toBe([
        ['name' => "profile-{$this->bob->id}", 'label' => 'Profile of Bob', 'icon' => 'heroicon-o-star', 'url' => "/profile/{$this->bob->id}"],
    ]);
});

it('says so when no convention fits, and falls back to a name for the label', function () {
    Schema::create('fake_workflows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->boolean('is_active')->default(true);
        $table->boolean('manual')->default(true);
        $table->string('model_class')->nullable();
    });

    // A model without a resource, on a page without a resource.
    $named = FakeWorkflow::create(['name' => 'Tidy up']);
    $nameless = FakeWorkflow::create();

    $lost = SplitButton::make([Action::make('open')->url('/open')])->options(FakeWorkflow::query())->livewire(new TestPage);

    expect(fn () => $lost->getFlatActions())->toThrow(LogicException::class, 'no convention says where it leads');

    $found = SplitButton::make([Action::make('open')->url('/open')])
        ->options(FakeWorkflow::query()->orderBy('id'), url: fn ($record): string => "/workflows/{$record->id}")
        ->livewire(new TestPage);

    expect(recordEntries($found))->toBe([
        ['name' => "fake-workflow-{$named->id}", 'label' => 'Tidy up', 'icon' => null, 'url' => "/workflows/{$named->id}"],
        ['name' => "fake-workflow-{$nameless->id}", 'label' => "FakeWorkflow #{$nameless->id}", 'icon' => null, 'url' => "/workflows/{$nameless->id}"],
    ]);
});

it('gives every table row the records of its own', function () {
    UsersTable::$recordActions = fn (): array => [
        SplitButton::make([Action::make('open')->label('Open')->url('/open')])
            ->options(fn (User $record) => User::query()->whereKeyNot($record->id), label: fn (User $user): string => "Switch to {$user->name}"),
    ];

    $html = Livewire::test(UsersTable::class)->html();

    expect(substr_count($html, 'Switch to Bob'))->toBe(1)
        ->and(substr_count($html, 'Switch to Ada'))->toBe(1);
});

it('marks the declared create action for keyboard shortcuts, wherever the user put it', function () {
    ListUsers::$headerActions = fn (): array => [
        SplitButton::make([CreateAction::make()])->editable()->options(User::query()->orderBy('id')),
    ];

    $key = headerButton(ListUsers::class)->getStateKey();

    // On the button: marked there.
    expect(Livewire::test(ListUsers::class)->html())
        ->toMatch('/<a[^>]*href="[^"]*\/admin\/users\/create"[^>]*data-mouseless="crud.create"[^>]*class="[^"]*fi-btn/s');

    // Another entry pinned to the button: the plain create link moves into
    // the menu and keeps the mark; the entry on the button gets none.
    app(SplitButtonManager::class)->save($key, new Layout(default: "user-{$this->bob->id}"));

    $html = Livewire::test(ListUsers::class)->html();

    expect(substr_count($html, 'data-mouseless="crud.create"'))->toBe(1)
        ->and($html)->toMatch('/<a[^>]*href="[^"]*\/admin\/users\/create"[^>]*data-mouseless="crud.create"[^>]*class="[^"]*fi-dropdown-list-item/s')
        ->and($html)->not->toMatch('/create\?user=\d+"[^>]*data-mouseless/');

    // Any other first action carries no mark.
    TestPage::$headerActions = fn (): array => [SplitButton::make([Action::make('open')->url('/open'), Action::make('more')->url('/more')])];

    expect(Livewire::test(TestPage::class)->html())->not->toContain('data-mouseless="crud.');
});
