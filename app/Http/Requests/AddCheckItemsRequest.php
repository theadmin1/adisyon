<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCheckItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'nullable|string',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.1',
            'items.*.notes' => 'nullable|string|max:255',
        ];
    }
}
