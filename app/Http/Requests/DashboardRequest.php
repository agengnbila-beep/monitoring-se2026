<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    /**
     * Hak akses sudah dijaga middleware `can:admin` pada route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'filters' => ['nullable', 'array', 'max:5'],
            'filters.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'filters.*.label' => ['required', 'string', 'max:100'],
            'filters.*.type' => ['required', Rule::in(['select', 'date_range'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama dashboard wajib diisi.',
        ];
    }
}
