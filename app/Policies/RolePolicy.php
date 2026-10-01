<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_role');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_role');
    }

    public function create(User $user): bool
    {
        return $user->can('create_role');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_role');
    }

    public function delete(User $user, $model): bool
    {
        if (in_array($model->name, ['admin', 'super_admin'])) {
            return false;
        }

        return $user->can('delete_role');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_role');
    }
}
