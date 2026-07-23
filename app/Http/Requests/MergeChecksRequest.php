<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeChecksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_check_ids' => 'required|array|min:1',
            'source_check_ids.*' => 'required|exists:checks,id',
        ];
    }
}
