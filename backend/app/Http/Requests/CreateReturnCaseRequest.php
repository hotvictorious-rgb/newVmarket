<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReturnCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === 'active';
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|integer',
            'collection_point_id' => 'nullable|integer',
            'return_reason' => 'required|string|in:defective_product,wrong_item_delivered,damaged_in_transit,item_not_as_described,change_of_mind',
            'customer_description' => 'required|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.item_condition' => 'nullable|string|in:unopened_sealed,opened_tested,damaged_defective,missing_accessories',
            'evidence' => 'nullable|array',
            'evidence.*' => 'nullable|string|max:500',
        ];
    }
}
