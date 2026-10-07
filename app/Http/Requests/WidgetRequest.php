<?php

namespace App\Http\Requests;

use App\Services\Dashboard\WidgetOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WidgetRequest extends FormRequest
{
    /**
     * Hak akses sudah dijaga middleware `can:admin` pada route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Isi `options` divalidasi oleh WidgetOptions karena bergantung
     * pada kolom hasil saved query yang dipilih.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'saved_query_id' => ['required', 'integer', 'exists:saved_queries,id'],
            'title' => ['required', 'string', 'max:255'],
            'chart_type' => ['required', Rule::in(WidgetOptions::CHART_TYPES)],
            'options' => ['present', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul widget wajib diisi.',
            'chart_type.in' => 'Jenis chart harus salah satu dari: '.implode(', ', WidgetOptions::CHART_TYPES).'.',
            'width.max' => 'Lebar widget maksimal 12 kolom grid.',
        ];
    }
}
