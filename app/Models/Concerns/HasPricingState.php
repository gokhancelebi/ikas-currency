<?php

namespace App\Models\Concerns;

trait HasPricingState
{
    /** Maliyet panelden girilmiş mi? */
    public function hasCostConfigured(): bool
    {
        return $this->price !== null && $this->price !== '';
    }

    /** Sync bu kayıt için fiyat güncellemesi yapabilir mi? */
    public function isSyncEnabled(): bool
    {
        return (bool) $this->sync_enabled;
    }
}
