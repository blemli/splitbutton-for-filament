<?php

namespace Blemli\SplitButton\Storage;

use Blemli\SplitButton\Layout;
use Blemli\SplitButton\Models\SplitButtonLayout;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Layouts per user in the database: the same menu on every device and in
 * every session.
 */
class DatabaseStore implements LayoutStore
{
    public function get(string $key): Layout
    {
        if (($query = $this->query($key)) === null) {
            return new Layout;
        }

        return Layout::fromArray($query->value('layout'));
    }

    public function put(string $key, Layout $layout): void
    {
        if (($columns = $this->userColumns()) === null) {
            return;
        }

        SplitButtonLayout::query()->updateOrCreate(
            [...$columns, 'key' => $key],
            ['layout' => $layout->toArray()],
        );
    }

    public function forget(string $key): void
    {
        $this->query($key)?->delete();
    }

    /**
     * @return Builder<SplitButtonLayout> | null
     */
    protected function query(string $key): ?Builder
    {
        if (($columns = $this->userColumns()) === null) {
            return null;
        }

        return SplitButtonLayout::query()->where($columns)->where('key', $key);
    }

    /**
     * @return array{user_type: string, user_id: string} | null
     */
    protected function userColumns(): ?array
    {
        if (($user = $this->user()) === null) {
            return null;
        }

        return [
            // Guards may hand out users that are no Eloquent models.
            'user_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
        ];
    }

    /**
     * The panel's user — all a guard promises is an Authenticatable.
     */
    protected function user(): ?Authenticatable
    {
        try {
            return Filament::auth()->user();
        } catch (Throwable) {
            return auth()->user();
        }
    }
}
