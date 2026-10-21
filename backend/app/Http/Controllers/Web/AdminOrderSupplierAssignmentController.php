<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrderSupplierAssignment;
use App\Services\SourcingAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminOrderSupplierAssignmentController extends Controller
{
    /**
     * Reassign selected items to a different confirmed Uyo supplier before payment.
     * Customer price and delivery fee remain unchanged; supplier cost snapshots and
     * retailer margin are recalculated atomically and an audit record is written.
     */
    public function override(
        Request $request,
        int $assignmentId,
        SourcingAllocationService $sourcingService
    ): RedirectResponse {
        $validated = $request->validate([
            'new_supplier_vendor_id' => 'required|integer|exists:vendor_profiles,id',
            'new_supplier_shop_id' => 'required|integer|exists:shops,id',
            'order_item_ids' => 'required|array|min:1|max:100',
            'order_item_ids.*' => 'required|integer|distinct|exists:order_items,id',
            'reason' => 'required|string|min:5|max:2000',
        ]);

        $assignment = OrderSupplierAssignment::findOrFail($assignmentId);

        try {
            $newAssignment = $sourcingService->overrideSupplierAssignmentGroup(
                $assignment,
                (int) $validated['new_supplier_vendor_id'],
                (int) $validated['new_supplier_shop_id'],
                $validated['order_item_ids'],
                $request->user(),
                $validated['reason']
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['supplier_override' => $exception->getMessage()])
                ->withInput();
        }

        return back()->with(
            'success',
            'Supplier assignment updated for the selected items. Review the order before payment is initiated.'
        );
    }
}
