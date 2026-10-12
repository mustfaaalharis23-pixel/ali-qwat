<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One package as the app offers it for selling: its key, its name in the request's language, and how many pieces it holds.
 *
 * @property Package $resource
 */
class PackageResource extends JsonResource
{
    /**
     * @return array{value: string, label: string, size: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource->value,
            'label' => $this->resource->getLabel(),
            'size' => $this->resource->size(),
        ];
    }
}
