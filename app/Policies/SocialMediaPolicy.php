<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SocialMediaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_social_media');
    }

    public function view(User $user, $model): bool
    {
        return $user->can('view_social_media');
    }

    public function create(User $user): bool
    {
        return $user->can('create_social_media');
    }

    public function update(User $user, $model): bool
    {
        return $user->can('update_social_media');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can('delete_social_media');
    }
}
