<?php

namespace App\Admin;

use App\Invoices\PurchaseExcelTemplate;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class ItemExcel
{
    public const string FILENAME = 'AKA-items-template.xlsx';

    public const int MAX_ROWS = 500;

    /** Tall enough for a photo to sit inside its cell. */
    private const int FILL_ROW_HEIGHT = 150;

    /** @param non-empty-list<string> $languages */
    public function template(string $path, array $languages): void
    {
        (new ExcelTemplate)->write(
            $path,
            array_map(fn (string $language): array => __('item_excel', [], $language), $languages),
            $languages[0] === 'ar',
            [40, 34, 38, 38, 40, 54, 40],
            [0, 2, 3, 5],
            self::FILL_ROW_HEIGHT,
        );
    }

    /**
     * @param  list<int>  $selection  Row numbers from the original workbook.
     */
    public function import(string $path, array $selection): int
    {
        sort($selection, SORT_NUMERIC);

        try {
            return Cache::lock('items-excel-import', 120)->block(5, fn (): int => DB::transaction(function () use ($path, $selection): int {
                $rows = $this->preview($path);
                $errors = [];
                foreach ($selection as $number) {
                    $row = $rows[$number] ?? null;
                    if ($row === null || $row['errors'] !== []) {
                        $errors["rows.{$number}"] = __('item_excel.invalid_row', ['row' => $number, 'message' => $row === null ? __('item_excel.missing_row') : reset($row['errors'])]);
                    }
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                foreach ($selection as $number) {
                    $row = $rows[$number];
                    Item::query()->create([
                        'name' => $row['name'],
                        'pieces_per_packet' => $row['pieces_per_packet'],
                        'item_category_id' => $row['category_id'],
                        'supplier_id' => $row['supplier_id'],
                        'sale_price' => $row['sale_price'] ?? 0,
                        'description' => $row['description'],
                    ]);
                }

                return count($selection);
            }));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['file' => __('item_excel.busy')]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function preview(string $path): array
    {
        $rows = $this->read($path);
        $names = $this->keys($rows, 'name');
        $repeated = collect($rows)->pluck('name')->filter(fn (?string $name): bool => $name !== null)->map($this->key(...))->countBy();
        $existing = Item::withTrashed()
            ->whereIn(DB::raw('lower(trim(name))'), $names)
            ->pluck('name')->map($this->key(...))->flip();
        $categories = ItemCategory::query()
            ->whereIn(DB::raw('lower(trim(name))'), $this->keys($rows, 'category'))
            ->get(['id', 'name'])->groupBy(fn (ItemCategory $category): string => $this->key($category->name));
        $supplierNames = $this->keys($rows, 'supplier');
        $suppliers = Supplier::query()
            ->where(fn ($query) => $query->whereIn(DB::raw('lower(trim(name_ar))'), $supplierNames)
                ->orWhereIn(DB::raw('lower(trim(name_zh))'), $supplierNames))
            ->get(['id', 'name_ar', 'name_zh'])
            ->flatMap(fn (Supplier $supplier): array => collect([$supplier->name_ar, $supplier->name_zh])
                ->filter(fn (?string $name): bool => filled($name))->map(fn (string $name): array => ['name' => $this->key($name), 'id' => $supplier->id])->all())
            ->groupBy('name');

        return collect($rows)->map(function (array $row, int $number) use ($repeated, $existing, $categories, $suppliers): array {
            $errors = [];
            $name = $row['name'] ?? null;
            $nameKey = $this->key($name ?? '');
            $nameError = match (true) {
                $name === null => 'name_required',
                mb_strlen($name) > 255 => 'name_too_long',
                $existing->has($nameKey) => 'existing_item',
                $repeated->get($nameKey, 0) > 1 => 'repeated_item',
                default => null,
            };
            if ($nameError !== null) {
                $errors['name'] = __("item_excel.{$nameError}");
            }

            $packet = $row['pieces_per_packet'] ?? null;
            if (! in_array($packet, array_map(strval(...), Item::PACKET_SIZES), true)) {
                $errors['pieces_per_packet'] = __('item_excel.invalid_packet', ['sizes' => implode(' / ', Item::PACKET_SIZES)]);
            }

            $categoryName = $row['category'] ?? null;
            $matchedCategories = $categories->get($this->key($categoryName ?? ''), collect());
            if ($categoryName !== null && $matchedCategories->count() !== 1) {
                $errors['category'] = $matchedCategories->isEmpty() ? __('item_excel.category_not_found') : __('item_excel.category_ambiguous');
            }

            $supplierName = $row['supplier'] ?? null;
            $supplierIds = $suppliers->get($this->key($supplierName ?? ''), collect())->pluck('id')->unique();
            if ($supplierName !== null && $supplierIds->count() !== 1) {
                $errors['supplier'] = $supplierIds->isEmpty() ? __('item_excel.supplier_not_found') : __('item_excel.supplier_ambiguous');
            }

            $rawPrice = $row['sale_price'] ?? null;
            $price = $this->price($rawPrice);
            if ($rawPrice !== null && $price === null) {
                $errors['sale_price'] = __('item_excel.invalid_price');
            }

            return [
                'row' => $number,
                'name' => $name,
                'pieces_per_packet' => $packet,
                'supplier' => $supplierName,
                'supplier_id' => $supplierIds->first(),
                'category' => $categoryName,
                'category_id' => $matchedCategories->first()?->id,
                'sale_price' => $price,
                'description' => $row['description'] ?? null,
                'errors' => $errors,
            ];
        })->all();
    }

    /** @return array<int, array<string, string>> */
    private function read(string $path): array
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $locales = array_keys(config('app.locales'));
        $labels = array_map(fn (string $locale): array => Arr::except(__('item_excel.columns', [], $locale), 'image'), $locales);
        $columns = [];
        $rows = [];

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number === 1) {
                        $title = $row->toArray();
                        if (in_array(PurchaseExcelTemplate::MARKER, $title, true) || ExcelTemplate::hasLocalizedTitle($title, 'purchase_excel')) {
                            throw ValidationException::withMessages(['file' => __('item_excel.purchase_template')]);
                        }
                    }
                    if ($number === 2) {
                        $columns = ExcelTemplate::columnPositions($row->toArray(), $labels, __('item_excel.wrong_template'));
                        if (! isset($columns['name'])) {
                            throw ValidationException::withMessages(['file' => __('item_excel.wrong_template')]);
                        }
                    }
                    if ($number < ExcelTemplate::FIRST_DATA_ROW) {
                        continue;
                    }

                    $fields = [];
                    foreach ($columns as $field => $position) {
                        $cell = $row->getCellAtIndex($position);
                        $value = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell?->getValue();
                        if (is_string($value) || is_int($value) || is_float($value)) {
                            $text = trim((string) $value);
                            if ($text !== '') {
                                $fields[$field] = $text;
                            }
                        }
                    }

                    if ($fields !== []) {
                        $rows[$number] = $fields;
                    }
                    if (count($rows) > self::MAX_ROWS) {
                        throw ValidationException::withMessages(['file' => __('item_excel.too_many_rows', ['count' => self::MAX_ROWS])]);
                    }
                }
                break;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('item_excel.unreadable')]);
        } finally {
            $reader->close();
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('item_excel.empty')]);
        }

        return $rows;
    }

    /** @param array<int, array<string, string>> $rows */
    private function keys(array $rows, string $field): Collection
    {
        return collect($rows)->pluck($field)->filter(fn (?string $value): bool => $value !== null)->map($this->key(...))->unique()->values();
    }

    private function key(string $text): string
    {
        return mb_strtolower(trim($text));
    }

    private function price(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/D', $value)) {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0 ? (float) $value : null;
    }
}
