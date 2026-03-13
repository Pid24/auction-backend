<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    // Mengambil seluruh data lelang di sistem tanpa batasan status
    public function getSystemData()
    {
        $auctions = Auction::with(['user'])->orderBy('created_at', 'desc')->get();
        return response()->json($auctions);
    }

    // Menghapus paksa lelang beserta aset visualnya (Override Command)
    public function forceDeleteAuction($id)
    {
        $auction = Auction::with('media')->findOrFail($id);

        // Pembersihan berkas fisik secara mutlak
        foreach ($auction->media as $media) {
            if (Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }
        }

        $auction->delete();

        return response()->json([
            'message' => 'ASSET PURGED FROM SYSTEM.'
        ]);
    }
}
