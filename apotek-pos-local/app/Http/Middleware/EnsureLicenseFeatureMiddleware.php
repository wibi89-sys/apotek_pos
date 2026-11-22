<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\LicenseFeature;
use App\Services\Licensing\LicenseEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseFeatureMiddleware
{
    public function __construct(
        protected LicenseEntitlementService $entitlement
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $featureEnum = LicenseFeature::tryFrom($feature);
        $featureName = $featureEnum ? $featureEnum->label() : $feature;

        if (! $this->entitlement->hasFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Fitur '{$featureName}' tidak termasuk dalam paket lisensi Anda. Silakan upgrade ke paket PRO atau Enterprise.",
                ], 403);
            }

            abort(403, "Fitur '{$featureName}' terkunci. Diperlukan lisensi tingkat PRO / Enterprise.");
        }

        return $next($request);
    }
}
