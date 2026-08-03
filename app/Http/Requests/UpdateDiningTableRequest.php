<?php

namespace App\Http\Requests;

use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hall_id' => [
                'nullable',
                Rule::exists('halls', 'id')
                    ->where(fn ($query) => $query->where('branch_id', $this->user()->branch_id)),
            ],
            'name' => 'required|string|max:100',
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('dining_tables', 'code')
                    ->where(fn ($query) => $query->where('branch_id', $this->user()->branch_id))
                    ->ignore($this->route('table')?->id),
            ],
            'capacity' => 'required|integer|min:1|max:100',
            'status' => ['nullable', Rule::enum(TableStatus::class)],
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
