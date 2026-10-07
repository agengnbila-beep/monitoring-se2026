<?php

namespace App\Http\Resources;

use App\Models\Dashboard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dashboard
 */
class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'filters' => $this->filters ?? [],
            'widgets' => WidgetResource::collection($this->whenLoaded('widgets')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
