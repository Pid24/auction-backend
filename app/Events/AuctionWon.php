<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionWon implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $auctionId;
    public $auctionTitle;
    public $winningAmount;

    public function __construct($userId, $auctionId, $auctionTitle, $winningAmount)
    {
        $this->userId = $userId;
        $this->auctionId = $auctionId;
        $this->auctionTitle = $auctionTitle;
        $this->winningAmount = $winningAmount;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'auction.won';
    }
}
