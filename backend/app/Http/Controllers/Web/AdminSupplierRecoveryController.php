<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SupplierReassignmentRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminSupplierRecoveryController extends Controller
{
    public function recover(
        Request $request,
        int $id,
        int $assignmentId,
        SupplierReassignmentRecoveryService $recoveryService
    ): RedirectResponse {
        $validated = $request->validate([
            'replacement_source' => ['required', 'string', 'regex:/^\d+:\d+$/'],
            'order_item_ids' => 'required|array|min:1|max:100',
            'order_item_ids.*' => 'required|integer|distinct|exists:order_items,id',
            'reason' => 'required|string|min:5|max:2000',
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        [$newSupplierVendorId, $newSupplierShopId] = array_map('intval', explode(':', $validated['replacement_source'], 2));

        try {
            $result = $recoveryService->reassignRejectedItems(
                $id,
                $assignmentId,
                $newSupplierVendorId,
                $newSupplierShopId,
                $validated['order_item_ids'],
                $request->user(),
                $validated['reason'],
                $validated['idempotency_key']
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['supplier_recovery' => $exception->getMessage()])
                ->withInput();
        }

        return back()->with('success', $result['message'] ?? 'Supplier recovery instruction recorded.');
    }
}
