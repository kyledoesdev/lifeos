<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * An on/off switch cached forever - clearing the cache switches the toggle off rather.
 */
trait Toggleable
{
    abstract protected function toggleKey(): string;

    public function enabled(): bool
    {
        return (bool) Cache::get($this->toggleKey(), false);
    }

    public function setEnabled(bool $enabled): void
    {
        Cache::forever($this->toggleKey(), $enabled);
    }
}
