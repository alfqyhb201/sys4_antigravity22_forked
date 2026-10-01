<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class IdeaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_idea');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_idea');
    }

    public function create(User $user): bool
    {
        return $user->can('create_idea');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_idea');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_idea');
    }
}
