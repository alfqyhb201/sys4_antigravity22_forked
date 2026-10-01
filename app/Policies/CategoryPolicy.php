<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_category');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_category');
    }

    public function create(User $user): bool
    {
        return $user->can('create_category');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_category');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_category');
    }
}
