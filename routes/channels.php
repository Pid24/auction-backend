<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('auction.{id}', function ($user, $id) {
    return $user !== null;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
