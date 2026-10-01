<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_invoice');
    }

    public function view(User $user, Invoice $model): bool
    {
        return $user->can('view_invoice');
    }

    public function create(User $user): bool
    {
        return $user->can('create_invoice');
    }

    public function update(User $user, Invoice $model): bool
    {
        return $user->can('update_invoice');
    }

    public function delete(User $user, Invoice $model): bool
    {
        return $user->can('delete_invoice');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_invoice');
    }

    public function restore(User $user, Invoice $model): bool
    {
        return $user->can('delete_invoice');
    }

    public function forceDelete(User $user, Invoice $model): bool
    {
        return $user->can('delete_invoice');
    }
}
