<?php

namespace Blemli\SplitButton\Storage;

use Blemli\SplitButton\Layout;

interface LayoutStore
{
    public function get(string $key): Layout;

    public function put(string $key, Layout $layout): void;

    public function forget(string $key): void;
}
