<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActiveTerminal;
use App\Services\Licensing\LicenseEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTerminalQuotaMiddleware
{
    public function __construct(
        protected LicenseEntitlementService $entitlement
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $terminalId = (string) config('license.terminal_id', 'POS01');
        $maxTerminals = $this->entitlement->getMaxTerminals();

        // If no limit or not set, allow
        if ($maxTerminals === null) {
            ActiveTerminal::ping($terminalId, auth()->id());
            return $next($request);
        }

        $activeThreshold = now()->subMinutes(15);

        // Check if current terminal is already active in last 15 min
        $currentIsActive = ActiveTerminal::where('terminal_id', $terminalId)
            ->where('last_ping_at', '>=', $activeThreshold)
            ->exists();

        $activeCount = ActiveTerminal::where('last_ping_at', '>=', $activeThreshold)->count();

        if (! $currentIsActive && $activeCount >= $maxTerminals) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Batas kuota terminal aktif ({$maxTerminals} terminal) telah tercapai.",
                ], 403);
            }

            abort(403, "Batas kuota terminal aktif ({$maxTerminals} terminal) tercapai. Silakan tutup sesi terminal lain atau upgrade lisensi.");
        }

        // Ping and proceed
        ActiveTerminal::ping($terminalId, auth()->id());

        return $next($request);
    }
}
