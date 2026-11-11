<?php

namespace Blemli\SplitButton\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A workflow the way the bridge sees one: a name, a switch, and maybe a
 * Manual trigger that names the kind of record it is for.
 */
class FakeWorkflow extends Model
{
    protected $table = 'fake_workflows';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['is_active' => 'boolean', 'manual' => 'boolean'];

    public function scopeForTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query;
    }

    /**
     * @return array<string, mixed> | null
     */
    public function triggerNode(string $trigger): ?array
    {
        if ((! $this->manual) || ($trigger !== FakeManualTrigger::class)) {
            return null;
        }

        return ['id' => "trigger-{$this->getKey()}", 'data' => ['config' => ['model_class' => $this->model_class]]];
    }
}
