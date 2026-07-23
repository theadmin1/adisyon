<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SplitCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'required|exists:check_items,id',
        ];
    }
}
