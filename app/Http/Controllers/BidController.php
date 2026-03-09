<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Events\BidPlaced;
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

                // 1. Validasi Pemilik: Pemilik tidak boleh menawar barangnya sendiri
                if ($auction->user_id === $request->user()->id) {
                    abort(403, 'Anda tidak bisa melakukan bid pada lelang Anda sendiri.');
                }

                // 2. Validasi Waktu: Lelang harus aktif
                $now = now();
                if ($now->lt($auction->start_time) || $now->gt($auction->end_time) || $auction->status !== 'active') {
                    abort(400, 'Lelang ini tidak sedang aktif.');
                }

                // 3. Validasi Nominal: Bid harus lebih besar dari current_price
                if ($request->bid_amount <= $auction->current_price) {
                    abort(400, 'Nominal bid harus lebih besar dari harga saat ini (' . $auction->current_price . ').');
                }

                // Simpan Bid
                $bid = Bid::create([
                    'auction_id' => $auction->id,
                    'user_id' => $request->user()->id,
                    'bid_amount' => $request->bid_amount,
                ]);

                // Update harga tertinggi di tabel lelang
                $auction->update([
                    'current_price' => $request->bid_amount,
                ]);

                return ['bid' => $bid, 'auction' => $auction];
            });

            // Trigger WebSocket Event setelah transaksi database sukses
            broadcast(new BidPlaced($result['bid'], $result['auction']))->toOthers();

            return response()->json([
                'message' => 'Bid berhasil ditempatkan.',
                'bid' => $result['bid']
            ], 201);

        } catch (\Exception $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }
}
