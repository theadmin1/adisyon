<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hall_id' => 'nullable|exists:halls,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'status' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
