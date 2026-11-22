<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Shift;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShiftIsOpenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass if requesting shift management page itself
        if ($request->routeIs('pos.shift*')) {
            return $next($request);
        }

        $terminalId = (string) config('license.terminal_id', 'POS01');
        $activeShift = Shift::currentOpenShift($terminalId);

        if (! $activeShift) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Shift kasir belum dibuka. Silakan buka shift kasir terlebih dahulu.',
                ], 403);
            }

            return redirect()->route('pos.shift')
                ->with('warning', 'Shift kasir belum dibuka. Masukkan modal awal untuk memulai transaksi POS.');
        }

        // Attach active shift to request
        $request->attributes->set('active_shift', $activeShift);

        return $next($request);
    }
}
