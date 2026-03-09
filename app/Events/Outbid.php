<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Gunakan ShouldBroadcastNow agar tidak tersangkut di antrean delay
class Outbid implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $auctionId;
    public $auctionTitle;
    public $newPrice;

    public function __construct($userId, $auctionId, $auctionTitle, $newPrice)
    {
        $this->userId = $userId;
        $this->auctionId = $auctionId;
        $this->auctionTitle = $auctionTitle;
        $this->newPrice = $newPrice;
    }

    public function broadcastOn()
    {
        // Mengirim khusus ke user ID spesifik
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'outbid.notification';
    }
}
