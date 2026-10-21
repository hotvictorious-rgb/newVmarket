<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SupplierOffer;
use App\Services\SupplierOfferAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSupplierOfferAvailabilityController extends Controller
{
    public function confirm(
        Request $request,
        int $offerId,
        SupplierOfferAvailabilityService $availabilityService
    ): RedirectResponse {
        $validated = $request->validate([
            'availability_expires_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // The role middleware protects this route; the service independently
        // checks the authenticated actor's active super-admin identity.
        $offer = SupplierOffer::query()->findOrFail($offerId);
        $availabilityService->confirm(
            $offer->id,
            $request->user(),
            Carbon::parse($validated['availability_expires_at']),
            $validated['notes'] ?? null,
            $request->ip(),
            substr((string) $request->userAgent(), 0, 1000)
        );

        return back()->with('success', 'Supplier availability confirmation recorded and audited.');
    }
}
