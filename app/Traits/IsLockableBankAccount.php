<?php

namespace App\Traits;

trait IsLockableBankAccount
{
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
