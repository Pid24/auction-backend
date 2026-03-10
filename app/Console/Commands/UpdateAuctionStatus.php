<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auction;
use Carbon\Carbon;

class UpdateAuctionStatus extends Command
{
    protected $signature = 'auctions:update-status';
    protected $description = 'Memperbarui status lelang berdasarkan waktu mulai dan selesai';

    public function handle()
    {
        $now = \Carbon\Carbon::now();

        // Eksekusi Pending -> Active
        $activated = \App\Models\Auction::where('status', 'pending')
            ->where('start_time', '<=', $now)
            ->update(['status' => 'active']);

        // Eksekusi Active -> Closed (Iterasi untuk mencari pemenang)
        $closingAuctions = \App\Models\Auction::where('status', 'active')
            ->where('end_time', '<=', $now)
            ->get();

        $closedCount = 0;
        foreach ($closingAuctions as $auction) {
            $auction->update(['status' => 'closed']);
            $closedCount++;

            // Cari penawar tertinggi di lelang yang baru saja ditutup
            $winningBid = $auction->bids()->orderBy('bid_amount', 'desc')->first();

            if ($winningBid) {
                // Tembakkan notifikasi privat ke pemenang
                broadcast(new \App\Events\AuctionWon(
                    $winningBid->user_id,
                    $auction->id,
                    $auction->title,
                    $winningBid->bid_amount
                ));
            }
        }

        $this->info("Task Executed: {$activated} activated, {$closedCount} closed.");
    }
}
