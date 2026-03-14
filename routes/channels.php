<?php

use Illuminate\Support\Facades\Broadcast;

// Otorisasi Saluran Privat: Hanya pemilik ID yang boleh mendengarkan event-nya sendiri
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Otorisasi Saluran Publik: Siapa saja boleh mendengarkan pembaruan ruang lelang
Broadcast::channel('auction.{id}', function ($user, $id) {
    return true;
});
