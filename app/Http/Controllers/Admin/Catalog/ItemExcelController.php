<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Admin\ItemExcel;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemExcelController extends Controller
{
    private const array FILE = ['required', 'file', 'extensions:xlsx', 'max:20480'];

    public function template(Request $request, ItemExcel $excel): StreamedResponse
    {
        Gate::authorize('create', Item::class);

        $supported = array_keys(config('app.locales'));
        $languages = $request->validate([
            'languages' => ['sometimes', 'required', 'array', 'list', 'max:'.count($supported)],
            'languages.*' => ['required', 'string', 'distinct', Rule::in($supported)],
        ])['languages'] ?? [app()->getLocale()];

        return response()->streamDownload(fn () => $excel->template('php://output', $languages), ItemExcel::FILENAME, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Request $request, ItemExcel $excel): JsonResponse
    {
        Gate::authorize('create', Item::class);

        $file = $request->validate(['file' => self::FILE])['file'];

        return response()->json(['rows' => array_values($excel->preview($file->getPathname()))]);
    }

    public function store(Request $request, ItemExcel $excel): JsonResponse
    {
        Gate::authorize('create', Item::class);

        $validated = $request->validate([
            'file' => self::FILE,
            'rows' => ['required', 'array', 'list', 'max:'.ItemExcel::MAX_ROWS],
            'rows.*' => ['required', 'integer', 'distinct', 'min:4'],
        ]);

        $count = $excel->import($validated['file']->getPathname(), array_map(intval(...), $validated['rows']));

        return response()->json(['count' => $count], 201);
    }
}
