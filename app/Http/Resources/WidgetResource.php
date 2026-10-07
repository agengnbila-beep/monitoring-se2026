<?php

namespace App\Http\Resources;

use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Widget
 */
class WidgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dashboard_id' => $this->dashboard_id,
            'saved_query_id' => $this->saved_query_id,
            'title' => $this->title,
            'chart_type' => $this->chart_type,
            'options' => $this->options,
            'sort_order' => $this->sort_order,
            'width' => $this->width,
            'data_url' => route('api.widgets.data', $this->resource),
        ];
    }
}
