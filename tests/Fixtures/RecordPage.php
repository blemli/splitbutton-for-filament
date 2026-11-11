<?php

namespace Blemli\SplitButton\Tests\Fixtures;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * A page about one record, the way a resource's view or edit page is.
 */
class RecordPage extends TestPage
{
    protected static ?string $slug = 'record';

    public function getRecord(): ?User
    {
        return User::query()->orderBy('id')->first();
    }

    public function getDefaultActionRecord(Action $action): ?Model
    {
        return $this->getRecord();
    }
}
