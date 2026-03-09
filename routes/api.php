<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\BidController;

// Paksa rute broadcasting untuk menggunakan prefix /api dan middleware sanctum
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// --- Endpoint Autentikasi ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Endpoint Lelang (Publik) ---
Route::get('/auctions', [AuctionController::class, 'index']);
Route::get('/auctions/{id}', [AuctionController::class, 'show']);

// --- Endpoint Terlindungi (Wajib Login / Bearer Token) ---
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Operasi Lelang (Create, Update, Delete)
    Route::post('/auctions', [AuctionController::class, 'store']);
    Route::put('/auctions/{id}', [AuctionController::class, 'update']);
    Route::delete('/auctions/{id}', [AuctionController::class, 'destroy']);

    // Transaksi Bidding
    Route::post('/auctions/{id}/bids', [BidController::class, 'store']);

    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'index']);
});
