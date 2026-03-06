<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Endpoint Publik (Tidak butuh token)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Endpoint Terlindungi (Wajib menyertakan token Bearer)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Endpoint untuk mengambil data profil pengguna yang sedang login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
