<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AuctionController extends Controller
{
    // 1. Read All (Publik): Menampilkan daftar lelang
    public function index()
    {
        // Mengambil lelang yang aktif atau tertunda, diurutkan dari tenggat waktu terdekat
        $auctions = Auction::whereIn('status', ['active', 'pending'])
                           ->orderBy('end_time', 'asc')
                           ->paginate(12);

        return response()->json($auctions);
    }

    // 2. Create (Terlindungi): Membuat lelang baru
    public function store(Request $request)
    {
        // Memundurkan komparasi waktu 'now' sebanyak 60 menit
        // untuk mengakomodasi perbedaan detik/menit antara frontend dan backend
        $toleranceTime = Carbon::now()->subMinutes(60)->toDateTimeString();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'starting_price' => 'required|numeric|min:0',
            'start_time' => 'required|date|after_or_equal:' . $toleranceTime,
            'end_time' => 'required|date|after:start_time',
        ]);

        // Menentukan status awal berdasarkan waktu mulai lelang
        $status = now()->gte($validated['start_time']) ? 'active' : 'pending';

        $auction = $request->user()->auctions()->create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'starting_price' => $validated['starting_price'],
            'current_price' => $validated['starting_price'], // Harga saat ini dimulai dari harga awal
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Lelang berhasil dibuat',
            'auction' => $auction
        ], 201);
    }

    // 3. Read Single (Publik): Menampilkan detail satu lelang beserta riwayat bid
    public function show($id)
    {
        $auction = Auction::with(['bids' => function($query) {
            $query->orderBy('bid_amount', 'desc');
        }, 'bids.user'])->findOrFail($id);

        return response()->json($auction);
    }

    // 4. Update (Terlindungi): Memperbarui data lelang
    public function update(Request $request, $id)
    {
        $auction = Auction::findOrFail($id);

        // Validasi Otorisasi: Hanya pembuat lelang yang boleh mengubah
        if ($request->user()->id !== $auction->user_id) {
            return response()->json(['message' => 'Akses ditolak. Anda bukan pemilik lelang ini.'], 403);
        }

        // Validasi Bisnis: Lelang tidak boleh diubah jika sudah ada penawaran
        if ($auction->bids()->exists()) {
            return response()->json(['message' => 'Lelang tidak dapat diubah karena sudah memiliki penawaran masuk.'], 400);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'starting_price' => 'sometimes|numeric|min:0',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
        ]);

        $auction->update($validated);

        // Menyelaraskan current_price jika starting_price diubah (dan belum ada bid)
        if (isset($validated['starting_price'])) {
            $auction->update(['current_price' => $validated['starting_price']]);
        }

        return response()->json([
            'message' => 'Data lelang berhasil diperbarui',
            'auction' => $auction
        ]);
    }

    // 5. Delete (Terlindungi): Menghapus lelang
    public function destroy(Request $request, $id)
    {
        $auction = Auction::findOrFail($id);

        // Validasi Otorisasi
        if ($request->user()->id !== $auction->user_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Validasi Bisnis
        if ($auction->bids()->exists()) {
            return response()->json(['message' => 'Lelang tidak dapat dihapus karena sudah memiliki penawaran masuk.'], 400);
        }

        $auction->delete();

        return response()->json(['message' => 'Lelang berhasil dihapus']);
    }
}
