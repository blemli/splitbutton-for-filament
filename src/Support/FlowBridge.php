<?php

namespace Blemli\SplitButton\Support;

use Blemli\SplitButton\SplitButton;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The only place that knows about packstub/filament-flow: it lists the
 * workflows a user may start by hand as menu entries and starts them, the
 * way that package's own RunWorkflowAction does. Neither package requires
 * the other — the classes are named in properties, never imported — and
 * without filament-flow this returns nothing.
 */
class FlowBridge
{
    public static string $flow = 'Packstub\\Flow\\Facades\\Flow';

    public static string $manualTrigger = 'Packstub\\Flow\\Nodes\\Triggers\\Manual';

    public static string $tenancy = 'Packstub\\Flow\\Support\\Tenancy';

    public const ENTRY_PREFIX = 'workflow-';

    /**
     * One entry per active workflow with a Manual trigger that takes the
     * kind of record this button is about (or, on a page without a
     * record, that asks for none).
     *
     * @param  array<int | string> | null  $only  workflow ids, null for all
     * @return array<Action>
     */
    public static function actions(SplitButton $button, ?array $only = null, bool $confirm = true): array
    {
        if (! static::isInstalled()) {
            return [];
        }

        try {
            $model = static::modelOf($button);
            $actions = [];

            foreach (static::workflows($only) as $workflow) {
                if (static::manualNode($workflow, $model) !== null) {
                    $actions[] = static::action($workflow, $confirm);
                }
            }

            return $actions;
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    public static function isInstalled(): bool
    {
        return class_exists(static::$flow) && class_exists(static::$manualTrigger);
    }

    protected static function action(Model $workflow, bool $confirm): Action
    {
        $id = $workflow->getKey();
        $name = (string) $workflow->getAttribute('name');

        $action = Action::make(static::ENTRY_PREFIX . $id)
            ->label($name)
            ->icon('heroicon-o-bolt')
            ->action(fn (Action $action) => static::run($id, $action->getRecord()));

        if ($confirm) {
            $action
                ->requiresConfirmation()
                ->modalHeading(__('splitbutton-for-filament::splitbutton.workflow_confirm', ['name' => $name]))
                ->modalSubmitActionLabel(__('splitbutton-for-filament::splitbutton.workflow_run'));
        }

        return $action;
    }

    /**
     * Start the workflow for the record (or for none) and say how it went,
     * in filament-flow's own words.
     *
     * @param  Model | array<string, mixed> | null  $record
     */
    public static function run(int | string $id, Model | array | null $record): void
    {
        $record = $record instanceof Model ? $record : null;

        // Looked up again: it may have been switched off since the menu was drawn.
        $workflow = collect(static::workflows([$id]))->first();
        $node = $workflow === null ? null : static::manualNode($workflow, $record === null ? null : $record::class);

        if (($workflow === null) || ($node === null)) {
            return;
        }

        $run = (static::$flow)::run(
            $workflow,
            $record === null ? ['manual' => true] : ['model' => $record, 'manual' => true],
            isset($node['id']) ? (string) $node['id'] : null,
        );

        $name = (string) $workflow->getAttribute('name');
        $status = $run?->getAttribute('status');
        $status = is_object($status) ? ($status->value ?? null) : $status;

        $notification = match (true) {
            $run === null => Notification::make()->info()->title(__('packstub-flow::flow.resource_action.queued', ['name' => $name, 'count' => 1])),
            $status === 'failed' => Notification::make()->danger()
                ->title(__('packstub-flow::flow.resource_action.failed', ['name' => $name, 'failed' => 1, 'total' => 1]))
                ->body((string) $run->getAttribute('error')),
            default => Notification::make()->success()->title(__('packstub-flow::flow.resource_action.done', ['name' => $name, 'count' => 1])),
        };

        $notification->send();
    }

    /**
     * @param  array<int | string> | null  $only
     * @return iterable<Model>
     */
    protected static function workflows(?array $only): iterable
    {
        $tenant = class_exists(static::$tenancy) ? (static::$tenancy)::panelTenant() : null;

        return (static::$flow)::workflowModel()::query()
            ->withoutGlobalScopes()
            ->forTenant($tenant)
            ->where('is_active', true)
            ->when($only !== null, fn ($query) => $query->whereIn('id', $only))
            ->orderBy('name')
            ->get();
    }

    /**
     * The workflow's Manual trigger, if it has one that takes this kind of
     * record: any kind when the trigger names none, and only a trigger that
     * names none when there is no record.
     *
     * @param  class-string | null  $model
     * @return array<string, mixed> | null
     */
    protected static function manualNode(Model $workflow, ?string $model): ?array
    {
        if (! method_exists($workflow, 'triggerNode')) {
            return null;
        }

        $node = $workflow->triggerNode(static::$manualTrigger);

        if (! is_array($node)) {
            return null;
        }

        $wanted = $node['data']['config']['model_class'] ?? null;

        if (blank($wanted)) {
            return $node;
        }

        return (($model !== null) && is_a($model, $wanted, allow_string: true)) ? $node : null;
    }

    /**
     * What the button is about: the model of its table, or of the record
     * the page shows.
     *
     * @return class-string | null
     */
    protected static function modelOf(SplitButton $button): ?string
    {
        try {
            if (($table = $button->getTable()) !== null) {
                return $table->getModel();
            }

            $livewire = $button->getLivewire();
            $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;

            return $record instanceof Model ? $record::class : null;
        } catch (Throwable) {
            return null;
        }
    }
}
