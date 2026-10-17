<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && in_array($user->user_type, ['vendor', 'admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'shop_id' => 'required|integer',
            'category_id' => 'required|integer',
            'unit_price' => 'required|numeric|min:1',
            'slash_price' => 'nullable|numeric|gt:unit_price',
            'condition' => 'required|string|in:brand_new,uk_used',
            'package_tier' => 'required|string|in:bike,keke,fragile,bulky',
            'warranty_type' => 'nullable|string|max:64',
            'inventory_quantity' => 'required|integer|min:0',
            'description' => 'nullable|string|max:5000',
            'photos' => 'required|array|min:1',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'At least one product photo is mandatory for listing.',
            'slash_price.gt' => 'Original price slash must be greater than selling unit price.',
        ];
    }
}
