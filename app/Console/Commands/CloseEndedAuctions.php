<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auction;
use App\Models\Wallet;
use App\Events\AuctionWon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloseEndedAuctions extends Command
{
    // Nama perintah untuk dieksekusi di terminal atau cron
    protected $signature = 'auctions:close';
    protected $description = 'Menutup lelang yang kedaluwarsa dan mengeksekusi pemotongan Escrow pemenang';

    public function handle()
    {
        // Cari semua lelang yang masih aktif tapi waktunya sudah lewat
        $auctions = Auction::where('status', 'active')
                           ->where('end_time', '<=', now())
                           ->get();

        if ($auctions->isEmpty()) {
            $this->info('Tidak ada lelang yang perlu ditutup saat ini.');
            return;
        }

        foreach ($auctions as $auction) {
            DB::transaction(function () use ($auction) {
                // Kunci baris untuk mencegah race condition
                $auction = Auction::lockForUpdate()->find($auction->id);
                if ($auction->status !== 'active') return;

                $highestBid = $auction->bids()->orderBy('bid_amount', 'desc')->first();

                if ($highestBid) {
                    $wallet = Wallet::where('user_id', $highestBid->user_id)->lockForUpdate()->first();

                    if ($wallet) {
                        // EKSEKUSI ESCROW MUTLAK: Potong uang yang ditahan dan uang asli
                        $wallet->frozen_balance -= $highestBid->bid_amount;
                        $wallet->balance -= $highestBid->bid_amount;
                        $wallet->save();

                        // Catat di buku besar sebagai pembayaran
                        $wallet->transactions()->create([
                            'amount' => $highestBid->bid_amount,
                            'type' => 'payment',
                            'reference_id' => $auction->id,
                        ]);
                    }

                    // Tembakkan Event AuctionWon milik Anda
                    broadcast(new AuctionWon(
                        $highestBid->user_id,
                        $auction->id,
                        $auction->title,
                        $highestBid->bid_amount
                    ));
                }

                // Kunci lelang secara permanen
                $auction->update(['status' => 'closed']);
            });

            $this->info("Lelang ID {$auction->id} ditutup. Escrow dieksekusi.");
        }
    }
}
