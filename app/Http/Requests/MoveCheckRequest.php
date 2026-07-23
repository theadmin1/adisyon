<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dining_table_id' => 'required|exists:dining_tables,id',
        ];
    }
}
