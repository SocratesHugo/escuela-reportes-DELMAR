<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GroupPolicy
{
    use HandlesAuthorization;

    protected function isAdminLike(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director', 'coordinador']);
    }

    public function viewAny(User $user): bool
    {
        // Todos los roles del panel pueden ver listado de grupos
        return $user->hasAnyRole([
            'admin','director','coordinador','preceptor','titular'
        ]);
    }

    public function view(User $user, Group $group): bool
    {
        if ($this->isAdminLike($user)) return true;

        if ($user->hasRole('titular')) {
            // Titular: solo ver su(s) propio(s) grupo(s)
            return (int) $group->titular_id === (int) $user->id;
        }

        if ($user->hasRole('preceptor')) {
            // Preceptor: puede ver (si luego quieres limitar por alumnos, lo hacemos)
            return true;
        }

        if ($user->hasRole('maestro')) {
            // Maestro: puede ver (listar/leer). Si quieres acotar por grupos donde da clase,
            // habría que cruzar por group_subject_teacher; dime y lo agregamos.
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->isAdminLike($user);
    }

    public function update(User $user, Group $group): bool
    {
        return $this->isAdminLike($user);
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->isAdminLike($user);
    }

    public function restore(User $user, Group $group): bool
    {
        return $this->isAdminLike($user);
    }

    public function forceDelete(User $user, Group $group): bool
    {
        return $this->isAdminLike($user);
    }
}
