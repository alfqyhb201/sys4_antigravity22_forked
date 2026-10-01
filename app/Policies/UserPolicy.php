<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_users');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('view_users');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_users');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($model->hasRole(['admin', 'super_admin']) && ! $user->hasRole(['admin', 'super_admin'])) {
            return false;
        }

        return $user->can('update_users');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($model->hasRole(['admin', 'super_admin']) && ! $user->hasRole(['admin', 'super_admin'])) {
            return false;
        }

        return $user->can('delete_users');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        if ($model->hasRole(['admin', 'super_admin']) && ! $user->hasRole(['admin', 'super_admin'])) {
            return false;
        }

        return $user->can('delete_users');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($model->hasRole(['admin', 'super_admin']) && ! $user->hasRole(['admin', 'super_admin'])) {
            return false;
        }

        return $user->can('delete_users');
    }
}
