<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Work;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkPolicy
{
    use HandlesAuthorization;

    protected function isAdminLike(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director', 'coordinador']);
    }

    protected function isOwnerTeacher(User $user, ?Work $work): bool
    {
        if (! $work || ! $work->relationLoaded('assignment')) {
            // Evitar N+1: si no viene precargado, consulta mínima.
            $work->loadMissing('assignment');
        }
        return (int) ($work->assignment->teacher_id ?? 0) === (int) $user->id;
    }

    public function viewAny(User $user): bool
    {
        // Todos los roles del panel pueden ver listado
        return $user->hasAnyRole([
            'admin','director','coordinador','maestro','preceptor','titular'
        ]);
    }

    public function view(User $user, Work $work): bool
    {
        if ($this->isAdminLike($user)) return true;
        if ($user->hasRole('maestro')) {
            // Maestro: solo si es su asignación
            return $this->isOwnerTeacher($user, $work);
        }
        if ($user->hasAnyRole(['preceptor','titular'])) {
            // Pueden ver (si deseas acotar por grupo del titular/preceptor, lo ajustamos)
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        if ($this->isAdminLike($user)) return true;
        // Maestro puede crear trabajos para sus propias asignaciones (se valida en el form y en update)
        return $user->hasRole('maestro');
    }

    public function update(User $user, Work $work): bool
    {
        if ($this->isAdminLike($user)) return true;
        if ($user->hasRole('maestro')) {
            return $this->isOwnerTeacher($user, $work);
        }
        return false;
    }

    public function delete(User $user, Work $work): bool
    {
        if ($this->isAdminLike($user)) return true;
        if ($user->hasRole('maestro')) {
            return $this->isOwnerTeacher($user, $work);
        }
        return false;
    }

    public function restore(User $user, Work $work): bool
    {
        return $this->delete($user, $work);
    }

    public function forceDelete(User $user, Work $work): bool
    {
        return $this->delete($user, $work);
    }
}
