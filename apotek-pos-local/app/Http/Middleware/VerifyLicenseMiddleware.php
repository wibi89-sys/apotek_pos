<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ClockTamperedException;
use App\Exceptions\LicenseInvalidException;
use App\Services\Licensing\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLicenseMiddleware
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Allow activation routes and emergency backup downloads without blocking
        if (
            $request->routeIs('license.*') ||
            $request->routeIs('db.emergency-download')
        ) {
            return $next($request);
        }

        try {
            $this->licenseService->verifyOffline();
        } catch (ClockTamperedException $e) {
            return redirect()->route('license.activate')
                ->with('error', 'PERINGATAN KRITIS: ' . $e->getMessage());
        } catch (LicenseInvalidException $e) {
            return redirect()->route('license.activate')
                ->with('error', 'LISENSI TIDAK VALID: ' . $e->getMessage());
        }

        return $next($request);
    }
}
