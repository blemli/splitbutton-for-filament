<?php

use Blemli\SplitButton\SplitButton;
use Blemli\SplitButton\Support\FlowBridge;
use Blemli\SplitButton\Tests\Fixtures\FakeFlow;
use Blemli\SplitButton\Tests\Fixtures\FakeManualTrigger;
use Blemli\SplitButton\Tests\Fixtures\FakeWorkflow;
use Blemli\SplitButton\Tests\Fixtures\RecordPage;
use Blemli\SplitButton\Tests\Fixtures\TestPage;
use Blemli\SplitButton\Tests\Fixtures\User;
use Blemli\SplitButton\Tests\Fixtures\UsersTable;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
 * ->workflows(): the workflows of packstub/filament-flow a user may start
 * by hand, as menu entries. That package is not installed here — the
 * bridge is pointed at stand-ins with the same few methods.
 */

beforeEach(function () {
    $this->originals = [FlowBridge::$flow, FlowBridge::$manualTrigger, FlowBridge::$tenancy];

    FlowBridge::$flow = FakeFlow::class;
    FlowBridge::$manualTrigger = FakeManualTrigger::class;
    FakeFlow::$runs = [];
    FakeFlow::$outcome = 'success';

    // filament-flow's own words for how a run went.
    app('translator')->addLines([
        'flow.resource_action.done' => '":name" ran for :count record(s).',
        'flow.resource_action.queued' => '":name" was queued for :count record(s).',
        'flow.resource_action.failed' => '":name" failed for :failed of :total record(s).',
    ], 'en', 'packstub-flow');

    Schema::create('fake_workflows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(true);
        $table->boolean('manual')->default(true);
        $table->string('model_class')->nullable();
    });

    $this->forAnything = FakeWorkflow::create(['name' => 'Nightly tidy-up']);
    $this->forUsers = FakeWorkflow::create(['name' => 'Welcome mail', 'model_class' => User::class]);
    FakeWorkflow::create(['name' => 'Switched off', 'is_active' => false]);
    FakeWorkflow::create(['name' => 'On a schedule', 'manual' => false]);
    FakeWorkflow::create(['name' => 'For something else', 'model_class' => FakeWorkflow::class]);
});

afterEach(function () {
    [FlowBridge::$flow, FlowBridge::$manualTrigger, FlowBridge::$tenancy] = $this->originals;
});

function entryNames(SplitButton $button): array
{
    return array_keys($button->getFlatActions());
}

it('ignores ->workflows() when filament-flow is not installed', function () {
    [FlowBridge::$flow, FlowBridge::$manualTrigger] = $this->originals;
    $this->signIn();

    TestPage::$headerActions = fn (): array => [SplitButton::make([Action::make('create')->label('Create')])->workflows()];

    expect(FlowBridge::isInstalled())->toBeFalse();

    Livewire::test(TestPage::class)
        ->assertSee('Create')
        ->assertDontSee('Nightly tidy-up')
        ->assertDontSeeHtml('fi-splitbutton-split');
});

it('lists nothing unless asked to', function () {
    $this->signIn();

    expect(entryNames(SplitButton::make([Action::make('create')])->livewire(new TestPage)))->toBe(['create'])
        ->and(entryNames(SplitButton::make([Action::make('create')])->workflows(false)->livewire(new TestPage)))->toBe(['create']);
});

it('lists, on a page without a record, the workflows that ask for none', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [SplitButton::make([Action::make('create')->label('Create')])->workflows()];

    Livewire::test(TestPage::class)
        ->assertSee('Nightly tidy-up')
        ->assertDontSee('Welcome mail')
        ->assertDontSee('Switched off')
        ->assertDontSee('On a schedule')
        ->assertDontSee('For something else');
});

it('asks before it runs a workflow, then runs it and says so', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [SplitButton::make([Action::make('create')])->workflows()];

    $entry = FlowBridge::ENTRY_PREFIX . $this->forAnything->id;

    $test = Livewire::test(TestPage::class)->mountAction($entry)->assertActionMounted($entry);

    expect($test->instance()->getMountedAction()->getModalHeading())->toBe('Run "Nightly tidy-up"?')
        ->and(FakeFlow::$runs)->toBe([]);

    $test->callMountedAction()->assertNotified('"Nightly tidy-up" ran for 1 record(s).');

    expect(FakeFlow::$runs)->toBe([
        ['workflow' => $this->forAnything->id, 'payload' => ['manual' => true], 'node' => "trigger-{$this->forAnything->id}"],
    ]);
});

it('runs straight away when told not to ask, and reports a queued or failed run', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [SplitButton::make([Action::make('create')])->workflows(confirm: false)];

    $entry = FlowBridge::ENTRY_PREFIX . $this->forAnything->id;

    FakeFlow::$outcome = null;
    Livewire::test(TestPage::class)->callAction($entry)->assertNotified('"Nightly tidy-up" was queued for 1 record(s).');

    FakeFlow::$outcome = 'failed';
    Livewire::test(TestPage::class)->callAction($entry)->assertNotified('"Nightly tidy-up" failed for 1 of 1 record(s).');

    expect(FakeFlow::$runs)->toHaveCount(2);
});

it('lists only the workflows named', function () {
    $this->signIn();

    $button = SplitButton::make([Action::make('create')])->workflows([$this->forAnything->id, 999])->livewire(new TestPage);

    expect(entryNames($button))->toBe(['create', FlowBridge::ENTRY_PREFIX . $this->forAnything->id]);
});

it('offers a table row the workflows for its kind of record, and runs them with that record', function () {
    $this->signIn();
    $bob = User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);

    UsersTable::$recordActions = fn (): array => [
        SplitButton::make([Action::make('open')->label('Open')->url('/open')])->workflows(confirm: false),
    ];

    $entry = FlowBridge::ENTRY_PREFIX . $this->forUsers->id;

    Livewire::test(UsersTable::class)
        ->assertSee('Welcome mail')
        ->assertSee('Nightly tidy-up')
        ->assertDontSee('For something else')
        ->callAction(TestAction::make($entry)->table($bob));

    expect(FakeFlow::$runs)->toHaveCount(1)
        ->and(FakeFlow::$runs[0]['workflow'])->toBe($this->forUsers->id)
        ->and(FakeFlow::$runs[0]['payload']['model']->is($bob))->toBeTrue()
        ->and(FakeFlow::$runs[0]['payload']['manual'])->toBeTrue();
});

it('offers a record page the workflows for its record, and runs them with it', function () {
    $ada = $this->signIn();
    TestPage::$headerActions = fn (): array => [SplitButton::make([Action::make('edit')->label('Edit')])->workflows(confirm: false)];

    Livewire::test(RecordPage::class)
        ->assertSee('Welcome mail')
        ->assertSee('Nightly tidy-up')
        ->assertDontSee('For something else')
        ->callAction(FlowBridge::ENTRY_PREFIX . $this->forUsers->id);

    expect(FakeFlow::$runs)->toHaveCount(1)
        ->and(FakeFlow::$runs[0]['payload']['model']->is($ada))->toBeTrue();
});

it('does not run a workflow that was switched off after the menu was drawn', function () {
    $this->signIn();
    TestPage::$headerActions = fn (): array => [SplitButton::make([Action::make('create')])->workflows(confirm: false)];

    $test = Livewire::test(TestPage::class);
    $this->forAnything->update(['is_active' => false]);

    FlowBridge::run($this->forAnything->id, null);

    expect(FakeFlow::$runs)->toBe([]);
});
