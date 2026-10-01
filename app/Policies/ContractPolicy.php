<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContractPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_contract');
    }

    public function view(User $user, Contract $model): bool
    {
        return $user->can('view_contract');
    }

    public function create(User $user): bool
    {
        return $user->can('create_contract');
    }

    public function update(User $user, Contract $model): bool
    {
        return $user->can('update_contract');
    }

    public function delete(User $user, Contract $model): bool
    {
        return $user->can('delete_contract');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_contract');
    }

    public function restore(User $user, Contract $model): bool
    {
        return $user->can('delete_contract');
    }

    public function forceDelete(User $user, Contract $model): bool
    {
        return $user->can('delete_contract');
    }
}
