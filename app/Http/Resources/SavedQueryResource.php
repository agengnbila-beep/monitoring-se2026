<?php

namespace App\Http\Resources;

use App\Models\SavedQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedQuery
 */
class SavedQueryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'dataset_id' => $this->dataset_id,
            'dataset_name' => $this->whenLoaded('dataset', fn () => $this->dataset?->name),
            'folder_id' => $this->folder_id,
            'name' => $this->name,
            'description' => $this->description,
            'config' => $this->config,
            'visualization' => $this->visualization,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
