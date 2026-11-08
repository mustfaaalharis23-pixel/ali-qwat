<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Every text of one language, flat, so the browser translates with the same keys as __():
 * "filament/resources/order.fields.customer" for lang/{locale}/filament/resources/order.php, and the JSON strings as they are.
 */
class TranslationController extends Controller
{
    public function __invoke(string $locale): JsonResponse
    {
        abort_unless(array_key_exists($locale, config('app.locales')), 404);

        $strings = [];
        $directory = lang_path($locale);

        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $group = Str::of($file->getRelativePathname())->beforeLast('.php')->replace('\\', '/')->value();
            $lines = require $file->getPathname();

            foreach (Arr::dot(is_array($lines) ? $lines : []) as $key => $line) {
                if (is_string($line)) {
                    $strings["{$group}.{$key}"] = $line;
                }
            }
        }

        $json = lang_path("{$locale}.json");

        if (File::exists($json)) {
            $strings += json_decode(File::get($json), true) ?: [];
        }

        return response()->json($strings)->header('Cache-Control', 'private, max-age=300');
    }
}
