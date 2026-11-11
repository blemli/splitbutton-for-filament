<?php

namespace Blemli\SplitButton\Storage;

use Blemli\SplitButton\Layout;

/**
 * The zero-setup store: layouts live in the session, so they are gone
 * after logout. ->persist() swaps in the DatabaseStore.
 */
class SessionStore implements LayoutStore
{
    public function get(string $key): Layout
    {
        return Layout::fromArray(session()->get($this->sessionKey($key)));
    }

    public function put(string $key, Layout $layout): void
    {
        session()->put($this->sessionKey($key), $layout->toArray());
    }

    public function forget(string $key): void
    {
        session()->forget($this->sessionKey($key));
    }

    /**
     * Button keys contain dots and backslashes, which the session would
     * read as nesting — hash them.
     */
    protected function sessionKey(string $key): string
    {
        return 'splitbutton.' . md5($key);
    }
}
