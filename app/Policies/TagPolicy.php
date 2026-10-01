<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TagPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_tag');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_tag');
    }

    public function create(User $user): bool
    {
        return $user->can('create_tag');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_tag');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_tag');
    }
}
