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
        $now = Carbon::now();

        // Eksekusi Pending -> Active
        $activated = Auction::where('status', 'pending')
            ->where('start_time', '<=', $now)
            ->update(['status' => 'active']);

        // Eksekusi Active -> Closed
        $closed = Auction::where('status', 'active')
            ->where('end_time', '<=', $now)
            ->update(['status' => 'closed']);

        $this->info("Task Executed: {$activated} activated, {$closed} closed.");
    }
}
