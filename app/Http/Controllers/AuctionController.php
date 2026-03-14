<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AuctionController extends Controller
{
    // 1. Read All (Publik): Menampilkan daftar lelang
    public function index(Request $request)
    {
        // Memuat relasi media dan kategori
        $query = Auction::with([
            'media' => function($query) {
                $query->orderBy('sort_order', 'asc');
            },
            'category'
        ])->whereIn('status', ['active', 'pending']);

        // Logika Filter Kategori berdasarkan slug
        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Diurutkan dari tenggat waktu terdekat
        $auctions = $query->orderBy('end_time', 'asc')->paginate(12);

        return response()->json($auctions);
    }

    // 2. Create (Terlindungi): Membuat lelang baru
    public function store(Request $request)
    {
        $toleranceTime = Carbon::now()->subMinutes(60)->toDateTimeString();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id', // Validasi kategori
            'description' => 'required|string',
            'starting_price' => 'required|numeric|min:0',
            'start_time' => 'required|date|after_or_equal:' . $toleranceTime,
            'end_time' => 'required|date|after:start_time',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // Maksimal 5MB per gambar
        ]);

        $status = now()->gte($validated['start_time']) ? 'active' : 'pending';

        $auction = $request->user()->auctions()->create([
            'title' => $validated['title'],
            'category_id' => $validated['category_id'], // Injeksi kategori saat create
            'description' => $validated['description'],
            'starting_price' => $validated['starting_price'],
            'current_price' => $validated['starting_price'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'status' => $status,
        ]);

        // Proses penyimpanan multi-gambar
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $file) {
                // Simpan ke storage/app/public/auctions
                $path = $file->store('auctions', 'public');

                // Daftarkan ke tabel auction_media
                $auction->media()->create([
                    'file_path' => $path,
                    'is_primary' => $index === 0 ? true : false, // Gambar pertama otomatis jadi thumbnail
                    'sort_order' => $index,
                ]);
            }
        }

        // Muat ulang relasi media dan kategori untuk dikembalikan di response
        $auction->load(['media', 'category']);

        return response()->json([
            'message' => 'Lelang berhasil dibuat',
            'auction' => $auction
        ], 201);
    }

    // 3. Read Single (Publik): Menampilkan detail satu lelang beserta riwayat bid, kategori, dan galeri
    public function show($id)
    {
        $auction = Auction::with([
            'category', // Memuat detail kategori
            'bids' => function($query) {
                $query->orderBy('bid_amount', 'desc');
            },
            'bids.user',
            'media' => function($query) {
                $query->orderBy('sort_order', 'asc');
            }
        ])->findOrFail($id);

        return response()->json($auction);
    }

    // 4. Update (Terlindungi): Memperbarui data lelang
    public function update(Request $request, $id)
    {
        $auction = Auction::findOrFail($id);

        if ($request->user()->id !== $auction->user_id) {
            return response()->json(['message' => 'Akses ditolak. Anda bukan pemilik lelang ini.'], 403);
        }

        if ($auction->bids()->exists()) {
            return response()->json(['message' => 'Lelang tidak dapat diubah karena sudah memiliki penawaran masuk.'], 400);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id', // Validasi update kategori
            'description' => 'sometimes|string',
            'starting_price' => 'sometimes|numeric|min:0',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
        ]);

        $auction->update($validated);

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

        if ($request->user()->id !== $auction->user_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        if ($auction->bids()->exists()) {
            return response()->json(['message' => 'Lelang tidak dapat dihapus karena sudah memiliki penawaran masuk.'], 400);
        }

        // Hapus fisik file gambar sebelum menghapus data dari database
        foreach ($auction->media as $media) {
            Storage::disk('public')->delete($media->file_path);
        }

        $auction->delete();

        return response()->json(['message' => 'Lelang berhasil dihapus']);
    }
}
