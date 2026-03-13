<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Akses ditolak. Otorisasi level Administrator diperlukan.'
            ], 403);
        }

        return $next($request);
    }
}
