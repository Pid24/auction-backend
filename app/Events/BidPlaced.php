<?php

namespace App\Events;

use App\Models\Bid;
use App\Models\Auction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bid;
    public $auction;

    public function __construct(Bid $bid, Auction $auction)
    {
        // Muat relasi user agar nama penawar ikut terkirim ke frontend
        $this->bid = $bid->load('user:id,name');
        $this->auction = $auction;
    }

    public function broadcastOn(): array
    {
        // Broadcast ke channel spesifik berdasarkan ID lelang
        return [
            new Channel('auction.' . $this->auction->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'bid.placed';
    }
}
