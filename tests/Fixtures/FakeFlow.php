<?php

namespace Blemli\SplitButton\Tests\Fixtures;

/**
 * Stands in for packstub/filament-flow's Flow facade: the two calls the
 * bridge makes.
 */
class FakeFlow
{
    /** @var array<int, array{workflow: int, payload: array<string, mixed>, node: string | null}> */
    public static array $runs = [];

    /** What run() answers: 'success', 'failed', or null for "queued". */
    public static ?string $outcome = 'success';

    public static function workflowModel(): string
    {
        return FakeWorkflow::class;
    }

    public static function run(FakeWorkflow $workflow, array $payload = [], ?string $startNodeId = null): ?FakeWorkflow
    {
        static::$runs[] = ['workflow' => $workflow->getKey(), 'payload' => $payload, 'node' => $startNodeId];

        if (static::$outcome === null) {
            return null;
        }

        // Any model does as a run: the bridge reads `status` and `error`.
        return (new FakeWorkflow)->forceFill(['status' => static::$outcome, 'error' => 'It broke.']);
    }
}
