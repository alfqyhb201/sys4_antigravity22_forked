<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComplaintPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_complaint');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_complaint');
    }

    public function create(User $user): bool
    {
        return $user->can('create_complaint');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_complaint');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_complaint');
    }
}
