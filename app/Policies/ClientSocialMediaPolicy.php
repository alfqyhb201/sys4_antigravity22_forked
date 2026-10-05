<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientSocialMediaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'social_media'])
            || $user->can('view_any_client_social_media');
    }

    public function view(User $user, $model): bool
    {
        return $user->hasAnyRole(['admin', 'social_media'])
            || $user->can('view_client_social_media');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'social_media'])
            || $user->can('create_client_social_media');
    }

    public function update(User $user, $model): bool
    {
        return $user->hasAnyRole(['admin', 'social_media'])
            || $user->can('update_client_social_media');
    }

    public function delete(User $user, $model): bool
    {
        return $user->hasAnyRole(['admin', 'social_media'])
            || $user->can('delete_client_social_media');
    }
}
