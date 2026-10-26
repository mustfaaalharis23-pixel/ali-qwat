<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One package as the app offers it for selling: its key and its name in the request's language. Its size is null,
 * because a packet's size is each item's own pieces per packet.
 *
 * @property Package $resource
 */
class PackageResource extends JsonResource
{
    /**
     * @return array{value: string, label: string, size: null}
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource->value,
            'label' => $this->resource->getLabel(),
            'size' => null,
        ];
    }
}
