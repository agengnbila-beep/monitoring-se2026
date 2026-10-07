<?php

namespace App\Http\Resources;

use App\Models\DatasetColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DatasetColumn
 */
class DatasetColumnResource extends JsonResource
{
    /**
     * Format kolom pada profil dataset (docs/api-contract.md bagian 2).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'position' => $this->position,
            'null_count' => $this->null_count,
            'unique_count' => $this->unique_count,
            'min' => $this->min_value,
            'max' => $this->max_value,
        ];
    }
}
