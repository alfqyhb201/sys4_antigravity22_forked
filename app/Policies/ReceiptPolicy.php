<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiptPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_receipt');
    }

    public function view(User $user, Receipt $model): bool
    {
        return $user->can('view_receipt');
    }

    public function create(User $user): bool
    {
        return $user->can('create_receipt') || $user->can('record_payment');
    }

    public function update(User $user, Receipt $model): bool
    {
        return $user->can('update_receipt') || $user->can('record_payment');
    }

    public function delete(User $user, Receipt $model): bool
    {
        return $user->can('delete_receipt');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_receipt');
    }

    public function restore(User $user, Receipt $model): bool
    {
        return $user->can('delete_receipt');
    }

    public function forceDelete(User $user, Receipt $model): bool
    {
        return $user->can('delete_receipt');
    }
}
