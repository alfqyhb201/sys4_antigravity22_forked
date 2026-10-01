<?php

namespace App\Policies;

use App\Models\DesignTask;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * سياسة التحكم بالوصول لمهام التصميم.
 */
class DesignTaskPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_design_task');
    }

    public function view(User $user, DesignTask $model): bool
    {
        return $user->can('view_design_task');
    }

    public function create(User $user): bool
    {
        return $user->can('create_design_task');
    }

    public function update(User $user, DesignTask $model): bool
    {
        return $user->can('update_design_task');
    }

    public function delete(User $user, DesignTask $model): bool
    {
        return $user->can('delete_design_task');
    }
}
