<?php

namespace App\Policies;

use App\Models\AutoCloseRule;
use App\Models\User;

class AutoCloseRulePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Si no está autenticado, niega
        if (!$user) return false;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, AutoCloseRule $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, AutoCloseRule $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, AutoCloseRule $model): bool
    {
        return $user->hasRole('admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, AutoCloseRule $model): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, AutoCloseRule $model): bool
    {
        return $user->hasRole('admin');
    }
}
