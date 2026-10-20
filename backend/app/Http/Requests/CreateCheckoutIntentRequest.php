<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === 'active';
    }

    public function rules(): array
    {
        return [
            'shop_id' => 'required|integer',
            'fulfillment_type' => 'required|string|in:doorstep_delivery,in_store_pickup',
            'customer_address_id' => 'required_if:fulfillment_type,doorstep_delivery|nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'redeem_cashback' => 'nullable|boolean',
            'redeem_wallet_credit' => 'nullable|boolean'
            'rider_id' => 'prohibited',
            'logistics_company_id' => 'prohibited',
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.quantity.max' => 'Maximum allowed quantity per line item is 1,000 units.',
        ];
    }
}
