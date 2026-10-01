<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DesignerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_designer');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_designer');
    }

    public function create(User $user): bool
    {
        return $user->can('create_designer');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_designer');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_designer');
    }
}
