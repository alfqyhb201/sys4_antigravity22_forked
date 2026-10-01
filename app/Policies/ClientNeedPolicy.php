<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientNeedPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_client_need');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_client_need');
    }

    public function create(User $user): bool
    {
        return $user->can('create_client_need');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_client_need');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_client_need');
    }
}
