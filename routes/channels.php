<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('auction.{id}', function ($user, $id) {
    return $user !== null;
});
