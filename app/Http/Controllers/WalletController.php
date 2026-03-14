<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function deposit(Request $request)
    {
        // Validasi ketat: Cegah injeksi nilai minus atau receh yang membebani server
        $request->validate([
            'amount' => 'required|numeric|min:10000|max:100000000',
        ], [
            'amount.min' => 'Nominal injeksi minimal adalah Rp 10.000.',
            'amount.max' => 'Nominal injeksi maksimal per eksekusi adalah Rp 100.000.000.',
        ]);

        try {
            $wallet = DB::transaction(function () use ($request) {
                // Kunci baris dompet spesifik ini selama transaksi berlangsung
                $wallet = $request->user()->wallet()->lockForUpdate()->first();

                if (!$wallet) {
                    abort(400, 'Entitas dompet tidak terdeteksi pada akun ini.');
                }

                // Eksekusi injeksi dana ke balance utama
                $wallet->balance += $request->amount;
                $wallet->save();

                // Pencatatan mutlak ke ledger (buku besar)
                $wallet->transactions()->create([
                    'amount' => $request->amount,
                    'type' => 'deposit',
                    'reference_id' => null, // Null karena ini top-up mandiri, bukan dari lelang
                ]);

                return $wallet;
            });

            // Muat ulang relasi transaksi terbaru untuk disinkronkan ke UI klien
            $wallet->load(['transactions' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }]);

            return response()->json([
                'message' => 'FUNDS INJECTED SUCCESSFULLY.',
                'wallet' => $wallet
            ], 200);

        } catch (\Exception $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }
}
