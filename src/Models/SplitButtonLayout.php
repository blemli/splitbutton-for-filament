<?php

namespace Blemli\SplitButton\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $user_type
 * @property string $user_id
 * @property string $key
 * @property array<string, mixed> $layout
 */
class SplitButtonLayout extends Model
{
    protected $guarded = [];

    protected $casts = [
        'layout' => 'array',
    ];

    public function getTable(): string
    {
        return config('splitbutton-for-filament.table', 'splitbutton_layouts');
    }
}
