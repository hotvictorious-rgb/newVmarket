<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SupplierOfferAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class VendorSupplierOfferAvailabilityController extends Controller
{
    public function markUnavailable(
        Request $request,
        int $offerId,
        SupplierOfferAvailabilityService $availabilityService
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        try {
            $availabilityService->markUnavailable(
                $offerId,
                $request->user(),
                $validated['reason'],
                $request->ip(),
                substr((string) $request->userAgent(), 0, 1000)
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Offer marked unavailable. It is excluded from new sourcing for at least 24 hours.');
    }
}
