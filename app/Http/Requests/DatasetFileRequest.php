<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi file yang diupload untuk Data baru maupun Update data.
 */
class DatasetFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'extensions:csv,xlsx,json', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file yang akan diupload.',
            'file.uploaded' => 'File gagal diupload. Ukurannya mungkin melebihi batas server.',
            'file.extensions' => 'Format file harus CSV, XLSX, atau JSON.',
            'file.max' => 'Ukuran file maksimal 20 MB.',
        ];
    }
}
