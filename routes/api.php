<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CategoryController;

// Paksa rute broadcasting untuk menggunakan prefix /api dan middleware sanctum
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// ==========================================
// 1. ZONA PUBLIK (Tanpa Otorisasi)
// ==========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/auctions', [AuctionController::class, 'index']);
Route::get('/auctions/{id}', [AuctionController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);

// ==========================================
// 2. ZONA TERLINDUNGI (Wajib Bearer Token)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Autentikasi & Profil Global ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('wallet');
    });
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::post('/wallet/deposit', [WalletController::class, 'deposit']);

    // --- Operasi Standar (Operative) ---
    // Membuat dan memanipulasi lelang milik sendiri
    Route::post('/auctions', [AuctionController::class, 'store']);
    Route::put('/auctions/{id}', [AuctionController::class, 'update']);
    Route::delete('/auctions/{id}', [AuctionController::class, 'destroy']);

    // Eksekusi Bidding
    Route::post('/auctions/{id}/bids', [BidController::class, 'store']);

    // ==========================================
    // 3. ZONA ADMINISTRATOR (Moderasi Sistem)
    // ==========================================
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Status Pengecekan
        Route::get('/status', function () {
            return response()->json(['message' => 'Otorisasi Administrator Tervalidasi. Akses Sistem Terbuka.'], 200);
        });

        // Kontrol Intervensi Data
        Route::get('/auctions', [\App\Http\Controllers\AdminController::class, 'getSystemData']);
        Route::delete('/auctions/{id}/force', [\App\Http\Controllers\AdminController::class, 'forceDeleteAuction']);
    });

});
