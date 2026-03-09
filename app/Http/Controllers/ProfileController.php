<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Auction;
use App\Models\Bid;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // Ekstraksi lelang yang dibuat oleh pengguna
        $myAuctions = Auction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Ekstraksi riwayat penawaran (bids) pengguna beserta data barangnya
        $myBids = Bid::with('auction')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'user' => $request->user(),
            'my_auctions' => $myAuctions,
            'my_bids' => $myBids
        ]);
    }
}
