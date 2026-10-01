<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustodyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_custody');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_custody');
    }

    public function create(User $user): bool
    {
        return $user->can('create_custody');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_custody');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_custody');
    }
}
