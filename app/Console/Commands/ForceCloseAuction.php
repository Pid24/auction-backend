<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auction;
use App\Models\Wallet;
use App\Events\AuctionWon;
use Illuminate\Support\Facades\DB;

class ForceCloseAuction extends Command
{
    // Command ini menerima argumen ID lelang yang ingin dieksekusi paksa
    protected $signature = 'auctions:force {id}';
    protected $description = 'Bypass sistem waktu dan eksekusi paksa penarikan Escrow lelang';

    public function handle()
    {
        $id = $this->argument('id');
        $auction = Auction::find($id);

        if (!$auction) {
            $this->error("Aset dengan ID {$id} tidak terdeteksi di mainframe.");
            return;
        }

        DB::transaction(function () use ($auction) {
            $auction = Auction::lockForUpdate()->find($auction->id);

            $highestBid = $auction->bids()->orderBy('bid_amount', 'desc')->first();

            if ($highestBid) {
                $wallet = Wallet::where('user_id', $highestBid->user_id)->lockForUpdate()->first();

                // Validasi ketat: Cegah penarikan dana ganda (Double Deduction)
                if ($wallet && $wallet->frozen_balance >= $highestBid->bid_amount) {
                    $wallet->frozen_balance -= $highestBid->bid_amount;
                    $wallet->balance -= $highestBid->bid_amount;
                    $wallet->save();

                    $wallet->transactions()->create([
                        'amount' => $highestBid->bid_amount,
                        'type' => 'payment',
                        'reference_id' => $auction->id,
                    ]);

                    // Tembakkan Notifikasi Kemenangan ke Frontend Next.js
                    broadcast(new AuctionWon(
                        $highestBid->user_id,
                        $auction->id,
                        $auction->title,
                        $highestBid->bid_amount
                    ));
                    $this->info("TRANSAKSI MUTLAK: Escrow Rp " . number_format($highestBid->bid_amount, 0, ',', '.') . " berhasil ditarik permanen.");
                } else {
                    $this->warn("ANOMALI ESCROW: Dana sudah ditarik sebelumnya atau saldo tertahan tidak sinkron.");
                }
            }

            // Kunci lelang secara paksa terlepas dari waktu
            $auction->update(['status' => 'closed']);
            $this->info("STATUS DIOVERRIDE: Lelang ID {$auction->id} dikunci paksa menjadi 'closed'.");
        });
    }
}
