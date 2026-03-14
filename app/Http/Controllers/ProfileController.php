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

        // 1. Ekstraksi Data Pengguna, Dompet, dan Mutasi Ledger
        $user = $request->user()->load([
            'wallet.transactions' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ]);

        // 2. Ekstraksi Aset yang Dipublikasikan (My Auctions) beserta Media Visual
        $myAuctions = Auction::where('user_id', $userId)
            ->with(['media' => function($query) {
                $query->orderBy('sort_order', 'asc');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // 3. Ekstraksi Riwayat Partisipasi (My Bids) - Di-filter unik per lelang
        $myBids = Bid::where('user_id', $userId)
            ->with(['auction.media' => function($query) {
                $query->orderBy('sort_order', 'asc');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('auction_id')
            ->values();

        // 4. Ekstraksi Aset Terakuisisi (Won Auctions)
        $wonAuctions = Auction::where('status', 'closed')
            ->join('bids', function ($join) use ($userId) {
                $join->on('auctions.id', '=', 'bids.auction_id')
                     ->where('bids.user_id', '=', $userId)
                     ->whereColumn('bids.bid_amount', 'auctions.current_price');
            })
            ->select('auctions.*')
            ->with(['media' => function($query) {
                $query->orderBy('sort_order', 'asc');
            }])
            ->orderBy('auctions.end_time', 'desc')
            ->get();

        return response()->json([
            'user' => $user,
            'my_auctions' => $myAuctions,
            'my_bids' => $myBids,
            'won_auctions' => $wonAuctions
        ]);
    }
}
