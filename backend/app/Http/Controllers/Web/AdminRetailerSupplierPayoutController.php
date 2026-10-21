<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\RetailerSupplierPayoutRequest;
use App\Services\BcMathService;
use App\Services\PaystackService;
use App\Services\RetailerSupplierPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminRetailerSupplierPayoutController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $payouts = RetailerSupplierPayoutRequest::with('supplier', 'allocations')
            ->latest('id')
            ->paginate(25);

        return view('admin.retailer-supplier-payouts.index', compact('payouts'));
    }

    public function disburse(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $payout = RetailerSupplierPayoutRequest::whereKey($id)->firstOrFail();
        if ($payout->status !== 'pending') {
            return back()->with('error', 'Only a pending supplier payout can be submitted.');
        }

        $paystack = app(PaystackService::class);
        $recipient = $paystack->createTransferRecipient(
            $payout->account_name,
            $payout->account_number,
            $payout->bank_code ?: $payout->bank_name
        );
        if (!$recipient['success']) {
            $reversal = app(RetailerSupplierPayoutService::class)->reverse(
                $payout->id,
                'not_submitted',
                'Recipient resolution failed before transfer submission: ' . ($recipient['message'] ?? 'Unknown recipient error')
            );
            return back()->with('error', $reversal['success']
                ? ('Supplier payout released before transfer: ' . ($recipient['message'] ?? 'Recipient resolution failed.'))
                : ($reversal['message'] ?? 'Supplier payout held for finance reconciliation.'));
        }

        $processing = app(RetailerSupplierPayoutService::class)->markProcessing(
            $payout->id,
            (int) $request->user()->id,
            (string) ($recipient['recipient_code'] ?? ''),
            'SUPPAYOUT-' . Str::upper(Str::random(24))
        );
        if (!$processing['success']) {
            return back()->with('error', $processing['message'] ?? 'Supplier payout could not enter processing.');
        }

        $payout = $processing['payout'];
        $transfer = $paystack->initiateTransfer(
            (string) $recipient['recipient_code'],
            (string) $payout->amount,
            'Retailer supplier payable payout for supplier #' . $payout->supplier_vendor_id,
            (string) $payout->transfer_reference
        );

        if (!$transfer['success']) {
            $isUncertain = !in_array($transfer['code'] ?? '', ['DEFINITIVE_REJECTION', 'CONFIG_MISSING', 'AMOUNT_BELOW_MINIMUM'], true);
            if ($isUncertain) {
                app(RetailerSupplierPayoutService::class)->markUncertain(
                    $payout->id,
                    $transfer['message'] ?? 'Transfer submission outcome is uncertain.',
                    $transfer['code'] ?? 'uncertain'
                );
                return back()->with('error', 'Supplier payout is held for gateway reconciliation. Do not retry it.');
            }

            $result = app(RetailerSupplierPayoutService::class)->reverse(
                $payout->id,
                'failed',
                $transfer['message'] ?? 'Paystack definitively rejected the transfer.'
            );
            return back()->with($result['success'] ? 'error' : 'error',
                $result['message'] ?? ($transfer['message'] ?? 'Supplier payout failed and requires reconciliation.'));
        }

        RetailerSupplierPayoutRequest::whereKey($payout->id)->update([
            'paystack_transfer_code' => $transfer['transfer_code'] ?? null,
        ]);

        return $this->verifyAndFinalize($payout->fresh(), $paystack, 'Supplier payout submitted; reconciliation result: ');
    }

    public function reconcile(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $payout = RetailerSupplierPayoutRequest::whereKey($id)->firstOrFail();
        if (!in_array($payout->status, ['processing', 'uncertain'], true)) {
            return back()->with('error', 'Only a processing or uncertain supplier payout can be reconciled.');
        }

        return $this->verifyAndFinalize($payout, app(PaystackService::class), 'Supplier payout reconciliation: ');
    }

    public function reject(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $payout = RetailerSupplierPayoutRequest::whereKey($id)->firstOrFail();
        if ($payout->status !== 'pending' || $payout->processing_token || $payout->gateway_status !== 'not_submitted') {
            return back()->with('error', 'This supplier payout may have reached the gateway. Reconcile it instead of rejecting it.');
        }

        $result = app(RetailerSupplierPayoutService::class)->reverse($payout->id, 'not_submitted', $validated['rejection_reason']);
        return back()->with($result['success'] ? 'status' : 'error', $result['message'] ?? 'Supplier payout rejection was processed.');
    }

    private function verifyAndFinalize(RetailerSupplierPayoutRequest $payout, PaystackService $paystack, string $prefix)
    {
        $verification = $paystack->verifyTransfer((string) $payout->transfer_reference);
        if (!$verification['success']) {
            app(RetailerSupplierPayoutService::class)->markUncertain(
                $payout->id,
                $verification['message'] ?? 'Paystack verification did not return an authoritative result.',
                'verification_unknown'
            );
            return back()->with('error', $prefix . 'gateway status is unknown; payout remains held.');
        }

        $gatewayStatus = strtolower((string) ($verification['status'] ?? ''));
        $data = $verification['data'] ?? [];
        if (empty($verification['is_simulated'])) {
            $expectedKobo = bcmul((string) $payout->amount, '100', 0);
            if (($data['reference'] ?? null) !== $payout->transfer_reference
                || (string) ($data['amount'] ?? '') !== $expectedKobo
                || strtoupper((string) ($data['currency'] ?? '')) !== 'NGN') {
                app(RetailerSupplierPayoutService::class)->markUncertain(
                    $payout->id,
                    'Paystack verification reference, amount, or currency does not match the supplier payout request.',
                    'verification_mismatch'
                );
                return back()->with('error', $prefix . 'verification evidence does not match; payout remains held.');
            }
        }

        if ($gatewayStatus === 'success') {
            $result = app(RetailerSupplierPayoutService::class)->finalizeSuccess(
                $payout->id,
                (string) $payout->transfer_reference,
                $data['transfer_code'] ?? $payout->paystack_transfer_code,
                (string) $payout->amount
            );
            return back()->with($result['success'] ? 'status' : 'error',
                $result['success'] ? ($prefix . 'paid and journaled.') : ($result['message'] ?? 'Supplier payout finalization is held.'));
        }

        if ($gatewayStatus === 'failed') {
            $result = app(RetailerSupplierPayoutService::class)->reverse($payout->id, 'failed', 'Paystack verified transfer failure.');
            return back()->with($result['success'] ? 'error' : 'error',
                $result['message'] ?? ($prefix . 'failed transfer reservation released.'));
        }

        if ($gatewayStatus === 'reversed') {
            $result = app(RetailerSupplierPayoutService::class)->reverse($payout->id, 'reversed', 'Paystack verified transfer reversal.');
            return back()->with($result['success'] ? 'error' : 'error',
                $result['message'] ?? ($prefix . 'gateway reversal recorded.'));
        }

        app(RetailerSupplierPayoutService::class)->markUncertain(
            $payout->id,
            'Paystack reports transfer status: ' . ($gatewayStatus ?: 'unknown'),
            $gatewayStatus ?: 'unknown'
        );
        return back()->with('error', $prefix . 'status remains ' . ($gatewayStatus ?: 'unknown') . '; do not retry.');
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !in_array($user->user_type, ['super_admin', 'admin', 'admin_employee'], true)) {
            abort(403, 'Admin access required.');
        }
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Super Admin approval is required for supplier payouts.');
        }
    }
}
