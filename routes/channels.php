<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{userId}', function ($user, int $userId): bool {
    return (int) $user->getKey() === $userId;
});
