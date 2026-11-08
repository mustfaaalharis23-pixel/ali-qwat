<?php

namespace App\Http\Controllers\Admin\Purchases\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A file chosen in a form is uploaded on its own first, to its folder of the public disk, like the panel's file fields do,
 * and the form then saves its path. Only a path in that folder is ever saved (see the form's rules).
 */
trait StoresUploads
{
    /**
     * @param  list<string>  $mimes
     */
    protected function storeUpload(Request $request, string $directory, array $mimes, int $maxKilobytes, string $attribute): JsonResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'mimes:'.implode(',', $mimes), "max:{$maxKilobytes}"]],
            attributes: ['file' => $attribute],
        );

        $path = $request->file('file')->store($directory, 'public');

        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }
}
