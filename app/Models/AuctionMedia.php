<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuctionMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'file_path',
        'is_primary',
        'sort_order',
    ];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
}
