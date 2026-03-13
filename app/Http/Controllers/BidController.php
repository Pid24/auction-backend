<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Wallet;
use App\Events\BidPlaced;
use App\Events\Outbid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BidController extends Controller
{
    public function store(Request $request, $auctionId)
    {
        $request->validate([
            'bid_amount' => 'required|numeric',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $auctionId) {
                // Kunci baris lelang ini di database sampai transaksi selesai (Pencegahan Race Condition)
                $auction = Auction::lockForUpdate()->findOrFail($auctionId);

                // 1. Validasi Pemilik
                if ($auction->user_id === $request->user()->id) {
                    abort(403, 'Anda tidak bisa melakukan bid pada lelang Anda sendiri.');
                }

                // 2. Validasi Waktu
                $now = now();
                if ($now->lt($auction->start_time) || $now->gt($auction->end_time) || $auction->status !== 'active') {
                    abort(400, 'Lelang ini tidak sedang aktif.');
                }

                // 3. Validasi Nominal
                if ($request->bid_amount <= $auction->current_price) {
                    abort(400, 'Nominal bid harus lebih besar dari harga saat ini (' . $auction->current_price . ').');
                }

                // 4. Validasi Saldo Escrow (Dompet Virtual)
                $wallet = $request->user()->wallet()->lockForUpdate()->first();
                if (!$wallet) {
                    abort(400, 'Sistem gagal mendeteksi entitas dompet untuk akun Anda.');
                }

                $availableBalance = $wallet->balance - $wallet->frozen_balance;
                if ($availableBalance < $request->bid_amount) {
                    abort(400, 'Saldo dompet tidak mencukupi. Dana tersedia: Rp ' . number_format($availableBalance, 0, ',', '.') . '.');
                }

                // Identifikasi penawar tertinggi sebelumnya (SEBELUM bid baru disimpan)
                $previousHighestBid = $auction->bids()->orderBy('bid_amount', 'desc')->first();

                // 5. Eksekusi Tahan Dana (Hold) untuk Penawar Baru
                $wallet->frozen_balance += $request->bid_amount;
                $wallet->save();

                $wallet->transactions()->create([
                    'amount' => $request->bid_amount,
                    'type' => 'hold',
                    'reference_id' => $auction->id,
                ]);

                // 6. Eksekusi Pengembalian Dana (Refund) untuk Penawar Sebelumnya
                if ($previousHighestBid && $previousHighestBid->user_id !== $request->user()->id) {
                    $prevWallet = Wallet::where('user_id', $previousHighestBid->user_id)->lockForUpdate()->first();
                    if ($prevWallet) {
                        // Mencegah anomali saldo negatif
                        $prevWallet->frozen_balance = max(0, $prevWallet->frozen_balance - $previousHighestBid->bid_amount);
                        $prevWallet->save();

                        $prevWallet->transactions()->create([
                            'amount' => $previousHighestBid->bid_amount,
                            'type' => 'refund',
                            'reference_id' => $auction->id,
                        ]);
                    }
                }

                // 7. Simpan Bid Baru
                $bid = Bid::create([
                    'auction_id' => $auction->id,
                    'user_id' => $request->user()->id,
                    'bid_amount' => $request->bid_amount,
                ]);

                // 8. Update harga tertinggi di tabel lelang
                $auction->update([
                    'current_price' => $request->bid_amount,
                ]);

                return [
                    'bid' => $bid,
                    'auction' => $auction,
                    'previousHighestBid' => $previousHighestBid
                ];
            });

            // Trigger WebSocket Event 1: Notifikasi Outbid (Private Channel)
            $previousHighestBid = $result['previousHighestBid'];
            if ($previousHighestBid && $previousHighestBid->user_id !== $request->user()->id) {
                broadcast(new Outbid(
                    $previousHighestBid->user_id,
                    $result['auction']->id,
                    $result['auction']->title,
                    $request->bid_amount
                ));
            }

            // Memuat relasi user agar object yang dikirim ke frontend memiliki nama penawar
            $result['bid']->load('user');

            // Trigger WebSocket Event 2: Update UI Room (Public Channel)
            broadcast(new BidPlaced($result['bid'], $result['auction']))->toOthers();

            return response()->json([
                'message' => 'TRANSMISSION ACCEPTED. DANA DITAHAN.',
                'bid' => $result['bid']
            ], 201);

        } catch (\Exception $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }
}
