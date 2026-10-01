<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TagGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_tag_group');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_tag_group');
    }

    public function create(User $user): bool
    {
        return $user->can('create_tag_group');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_tag_group');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_tag_group');
    }
}
