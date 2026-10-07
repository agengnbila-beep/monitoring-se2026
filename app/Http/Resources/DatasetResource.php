<?php

namespace App\Http\Resources;

use App\Models\Dataset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dataset
 */
class DatasetResource extends JsonResource
{
    /**
     * Format daftar dataset sesuai docs/api-contract.md bagian 1.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'file_type' => $this->file_type,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'row_count' => $this->row_count,
            'column_count' => $this->column_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
