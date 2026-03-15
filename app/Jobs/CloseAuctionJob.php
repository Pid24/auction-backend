<?php

namespace App\Jobs;

use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CloseAuctionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $auction;

    public function __construct(Auction $auction)
    {
        $this->auction = $auction;
    }

    public function handle(): void
    {
        // Tarik data terbaru dari pangkalan data untuk memvalidasi pembaruan end_time (Anti-Sniping)
        $auction = Auction::with(['bids' => function($q) {
            $q->orderBy('bid_amount', 'desc')->first();
        }])->find($this->auction->id);

        // Gagalkan eksekusi jika lelang sudah ditutup atau tidak ditemukan
        if (!$auction || $auction->status === 'closed') {
            return;
        }

        // Jika waktu saat ini masih di bawah end_time (karena perpanjangan Anti-Sniping), hentikan Job ini.
        // Job baru dengan delay yang lebih lama pasti sudah di-dispatch oleh BidController.
        if (now()->lt($auction->end_time)) {
            return;
        }

        // Eksekusi mutlak penutupan lelang
        $auction->update(['status' => 'closed']);

        $highestBid = $auction->bids->first();

        if ($highestBid) {
            // TODO: Integrasi logika pemotongan Escrow permanen dari dompet pemenang ke dompet kreator
            // dan rilis (refund) frozen_balance milik bidder lain di sini.
        }
    }
}
