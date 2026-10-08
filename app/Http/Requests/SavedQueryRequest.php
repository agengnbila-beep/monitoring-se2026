<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SavedQueryRequest extends FormRequest
{
    /**
     * Hak akses sudah dijaga middleware `can:admin` pada route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Isi `config` divalidasi oleh QueryEngine karena aturannya
     * bergantung pada kolom dataset yang dipilih.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'config' => ['present', 'array'],
            'folder_id' => ['nullable', 'integer', 'exists:query_folders,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama query wajib diisi.',
            'dataset_id.exists' => 'Dataset tidak ditemukan.',
        ];
    }
}
