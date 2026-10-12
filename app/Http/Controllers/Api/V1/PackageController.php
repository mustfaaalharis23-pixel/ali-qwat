<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Package;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PackageResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
    /**
     * The packages the app offers when selling, one list for all the items, from the smallest.
     * The list lives on the server only, so changing it reaches the app without a new version.
     */
    public function index(): AnonymousResourceCollection
    {
        return PackageResource::collection(Package::cases());
    }
}
