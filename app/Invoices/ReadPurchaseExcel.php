<?php

namespace App\Invoices;

use App\Admin\ExcelTemplate;
use App\Models\Color;
use App\Models\Item;
use App\Models\LensPower;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Reads a supplier's order in Excel into the lines of a new purchase invoice, without saving anything.
 *
 * The layout is the supplier's: the headers on the second row, then from the fourth row one row for each item: its code (B),
 * its powers ("+50-25box" is 25 packets of power 0.50, C), its colors ("black 12" is 12 black pieces in each packet, E),
 * its cartons (F), pieces a carton (G), pieces a packet (H), packets (I), pieces (J), price of a piece in yuan (K), and amount (L).
 * An item may go on over more rows, each with its own packets and price; a row with numbers and no price holds the totals.
 *
 * Nothing in the file is dropped: every item becomes a line (an item over two prices becomes one line at the average price of
 * its pieces, so its amount stays exact), every row is part of an item, a note, or the totals, or is reported, and every
 * number the file writes is checked against what it is made of. An issue is an error when the invoice cannot be saved as
 * read, so it is fixed first, or a warning to be seen.
 *
 * @phpstan-type Issue array{type: string, level: string, row: ?int, values: array<string, mixed>, message: string}
 */
class ReadPurchaseExcel
{
    public const string ERROR = 'error';

    public const string WARNING = 'warning';

    private const int HEADER_ROW = 2;

    private const int FIRST_ROW = 4;

    /**
     * The headers the layout has on its second row, by their column, in lower case.
     *
     * @var array<string, string>
     */
    private const array HEADERS = [
        'B' => 'mark',
        'C' => 'name',
        'F' => 'ctns',
        'G' => 'ctns/pcs',
        'H' => 'box',
        'I' => 'all box',
        'J' => 'all pcs',
        'K' => 'price',
        'L' => 'amount',
    ];

    /**
     * How far apart two amounts may be and still be the same: under one fils of a yuan.
     */
    private const float TOLERANCE = 0.01;

    private const array TEMPLATE_NUMBERS = [
        'B' => 'packets',
        'C' => 'pieces_per_packet',
        'D' => 'boxes_per_carton',
        'E' => 'price',
        'F' => 'carton_cbm',
    ];

    /**
     * "+50-25box": 25 packets of power 0.50, or "+1.50-25box" written as the power itself.
     */
    private const string POWER = '/\+?\s*(\d+(?:\.\d+)?)\s*-\s*(\d+)\s*box(?:es)?/iu';

    /**
     * "black 12": 12 black pieces in each packet, the pieces even on the next line.
     */
    private const string COLOR = '/([^\d\r\n,\/;，、|]+?)\s*(\d+)/u';

    /**
     * What may stand between powers or colors.
     */
    private const string SEPARATORS = '/[\s\/,;，、|]+/u';

    /**
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     issues: list<Issue>,
     *     summary: array{written: array{packets: int|float|null, cartons: int|float|null, amount: int|float|null}, read: array{items: int, rows: int, packets: int|float|null, cartons: int|float|null, pieces: int|float|null, amount: float|null}},
     * }
     *
     * @throws ValidationException when the file cannot be read, is not in the layout, or has no item.
     */
    public function handle(string $path): array
    {
        $rows = $this->rows($path);
        if (ExcelTemplate::hasLocalizedTitle($rows[1] ?? [], 'item_excel')) {
            throw ValidationException::withMessages(['file' => __('invoices.excel.items_template')]);
        }

        $translations = array_map(PurchaseExcelTemplate::translations(...), array_keys(config('app.locales')));
        $templateColumns = ExcelTemplate::columnPositions($rows[self::HEADER_ROW] ?? [], array_column($translations, 'columns'), __('invoices.excel.not_the_template'));
        $titles = [PurchaseExcelTemplate::MARKER, ...array_column($translations, 'order_title')];
        if (array_any($rows[1] ?? [], fn (mixed $value): bool => in_array($value, $titles, true)) || count($templateColumns) >= 2) {
            return $this->readTemplate($rows, $templateColumns);
        }

        $this->ensureTheLayout($rows[self::HEADER_ROW] ?? []);

        ['items' => $items, 'totals' => $written, 'issues' => $issues] = $this->group($rows);

        if ($items === []) {
            throw ValidationException::withMessages(['file' => __('invoices.excel.no_items')]);
        }

        $known = $this->known($items);
        $lines = array_map(fn (array $item): array => $this->line($item, $known), $items);
        $read = $this->summary($lines);

        foreach (['packets' => 'total_packets_differs', 'cartons' => 'total_cartons_differs', 'amount' => 'total_amount_differs'] as $total => $type) {
            if ($written[$total] !== null && abs($written[$total] - $read[$total]) > self::TOLERANCE) {
                $issues[] = $this->issue($type, self::WARNING, ['written' => $written[$total], 'read' => $read[$total]]);
            }
        }

        return ['lines' => $lines, 'issues' => $issues, 'summary' => ['written' => $written, 'read' => $read]];
    }

    /**
     * The first sheet's rows by their number, each cell by its column letter: a formula by the value it last gave,
     * and an empty cell as null.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $path): array
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $rows = [];

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    $cells = [];

                    foreach ($row->getCells() as $index => $cell) {
                        $value = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();

                        if ($index < 26 && ! (is_string($value) && trim($value) === '') && $value !== null) {
                            $cells[chr(ord('A') + $index)] = $value;
                        }
                    }

                    if ($cells !== []) {
                        $rows[$number] = $cells;
                    }
                }

                break;
            }
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('invoices.excel.unreadable')]);
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $headers
     *
     * @throws ValidationException
     */
    private function ensureTheLayout(array $headers): void
    {
        foreach (self::HEADERS as $column => $header) {
            if (mb_strtolower($this->text($headers[$column] ?? null) ?? '') !== $header) {
                throw ValidationException::withMessages(['file' => __('invoices.excel.not_the_template')]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $columns
     * @return array<string, mixed>
     */
    private function readTemplate(array $rows, array $columns): array
    {
        if ($columns === []) {
            throw ValidationException::withMessages(['file' => __('invoices.excel.not_the_template')]);
        }

        $items = [];
        foreach ($rows as $number => $row) {
            if ($number < PurchaseExcelTemplate::FIRST_DATA_ROW) {
                continue;
            }

            $cells = array_filter(array_map(fn (string $position): mixed => $row[$position] ?? null, $columns), fn (mixed $value): bool => $value !== null);
            if ($cells !== []) {
                $items[$number] = ['code' => $this->text($cells['A'] ?? null) ?? '', 'cells' => $cells];
            }
        }

        if ($items === []) {
            throw ValidationException::withMessages(['file' => __('invoices.excel.no_items')]);
        }

        $known = $this->known($items);
        $lines = [];
        foreach ($items as $number => $item) {
            $lines[] = $this->templateLine($item['code'], $item['cells'], $known, $number);
        }

        return [
            'lines' => $lines,
            'issues' => [],
            'summary' => [
                'written' => ['packets' => null, 'cartons' => null, 'amount' => null],
                'read' => $this->summary($lines, requireEveryLine: true),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{items: Collection<string, EloquentCollection<int, Item>>, colors: Collection<string, Color>, powers: EloquentCollection<int, LensPower>}  $known
     * @return array<string, mixed>
     */
    private function templateLine(string $code, array $row, array $known, int $number): array
    {
        $issues = [];
        $missing = [];
        $invalid = [];
        $values = [];
        foreach (self::TEMPLATE_NUMBERS as $column => $field) {
            $raw = $row[$column] ?? null;
            $value = $this->number($raw);
            $valid = $value !== null && ($field === 'price' ? $value >= 0 : $value > 0)
                && (! in_array($field, ['packets', 'pieces_per_packet'], true) || is_int($value));

            if ($raw === null) {
                $missing[] = $field;
            } elseif (! $valid) {
                $invalid[] = $field;
            }

            $values[$field] = $valid ? $value : null;
        }

        if ($code === '') {
            $issues[] = $this->issue('missing_item_code', self::ERROR, row: $number);
        }
        if ($missing !== []) {
            $issues[] = $this->issue('missing_numbers', self::ERROR, ['fields' => $missing], $number);
        }
        if ($invalid !== []) {
            $issues[] = $this->issue('invalid_numbers', self::ERROR, ['fields' => $invalid], $number);
        }

        $packets = $values['packets'];
        $packet = $values['pieces_per_packet'];
        $boxesPerCarton = $values['boxes_per_carton'];
        $price = $values['price'];
        $cartons = $packets !== null && $boxesPerCarton !== null ? ceil($packets / $boxesPerCarton) : null;
        $pieces = $packets !== null && $packet !== null ? $packets * $packet : null;
        $amount = $pieces !== null && $price !== null ? round($pieces * $price, 2) : null;

        foreach ([
            'I' => ['cartons', 'cartons_differ', $cartons],
            'J' => ['pieces', 'pieces_differ', $pieces],
            'K' => ['amount', 'amount_differs', $amount],
        ] as $column => [$field, $type, $expected]) {
            if (! isset($row[$column])) {
                continue;
            }

            $written = $this->number($row[$column]);
            if ($written === null || $written < 0 || ($field !== 'amount' && ! is_int($written))) {
                $issues[] = $this->issue('invalid_numbers', self::WARNING, ['fields' => [$field]], $number);
            } elseif ($expected !== null) {
                $this->compare($issues, $type, $written, $expected, $number);
            }
        }

        $item = $code === '' ? null : $this->matchItem($code, $packet, $known['items'], $issues, $number);
        $groups = $this->groups($this->text($row['H'] ?? null), $known['powers'], $issues, $number, template: true);
        $colors = $this->colors($this->text($row['G'] ?? null), $known['colors'], $issues, $number, template: true);
        $this->checkBreakdowns($issues, $number, $packets, $packet, $groups, $colors);

        return [
            'code' => $code,
            'rows' => [$number],
            'item' => $item,
            'packets' => $packets,
            'pieces_per_packet' => $packet,
            'boxes_per_carton' => $boxesPerCarton,
            'cartons' => $cartons === null ? null : $this->tidy($cartons),
            'carton_cbm' => $values['carton_cbm'],
            'unit_cost' => $price,
            'amount' => $amount,
            'colors' => $colors,
            'groups' => $groups,
            'issues' => $issues,
        ];
    }

    /**
     * The items with their rows, and the totals the file writes: a row with a code starts an item, a row with a price goes on
     * with the item above it (or is reported when there is none), a row with numbers and no price holds totals, and a row of
     * text only is a note.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{items: list<array{code: string, rows: array<int, array<string, mixed>>}>, totals: array{packets: int|float|null, cartons: int|float|null, amount: int|float|null}, issues: list<Issue>}
     */
    private function group(array $rows): array
    {
        $items = [];
        $totals = ['packets' => null, 'cartons' => null, 'amount' => null];
        $issues = [];

        foreach ($rows as $number => $row) {
            if ($number < self::FIRST_ROW) {
                continue;
            }

            $code = $this->text($row['B'] ?? null);

            if ($code !== null) {
                $items[] = ['code' => $code, 'rows' => [$number => $row]];

                continue;
            }

            if ($this->number($row['K'] ?? null) !== null) {
                if ($items === []) {
                    $issues[] = $this->issue('row_without_item', self::ERROR, row: $number);
                } else {
                    $items[array_key_last($items)]['rows'][$number] = $row;
                }

                continue;
            }

            $totals['cartons'] ??= $this->number($row['F'] ?? null);
            $totals['packets'] ??= $this->number($row['I'] ?? null);
            $totals['amount'] = $this->number($row['L'] ?? null) ?? $totals['amount'];
        }

        return ['items' => $items, 'totals' => $totals, 'issues' => $issues];
    }

    /**
     * The items, colors, and powers of the system the file's names and values point to.
     *
     * @param  array<int, array{code: string}>  $items
     * @return array{items: Collection<string, EloquentCollection<int, Item>>, colors: Collection<string, Color>, powers: EloquentCollection<int, LensPower>}
     */
    private function known(array $items): array
    {
        $codes = collect($items)->pluck('code')->filter(fn (string $code): bool => $code !== '')
            ->map(fn (string $code): string => mb_strtolower($code))->unique()->values()->all();

        return [
            'items' => Item::query()
                ->active()
                ->with('specificDescription')
                ->whereIn(DB::raw('lower(trim(name))'), $codes)
                ->get()
                ->groupBy(fn (Item $item): string => mb_strtolower(trim($item->name))),
            'colors' => Color::query()->get()->keyBy(fn (Color $color): string => mb_strtolower(trim($color->name))),
            'powers' => LensPower::query()->get(),
        ];
    }

    /**
     * One item of the file as a line of the invoice, with everything found wrong in it.
     *
     * @param  array{code: string, rows: array<int, array<string, mixed>>}  $item
     * @param  array{items: Collection<string, EloquentCollection<int, Item>>, colors: Collection<string, Color>, powers: EloquentCollection<int, LensPower>}  $known
     * @return array<string, mixed>
     */
    private function line(array $item, array $known): array
    {
        $first = reset($item['rows']);
        $firstRow = (int) array_key_first($item['rows']);
        $packet = $this->number($first['H'] ?? null);
        $perCarton = $this->number($first['G'] ?? null);
        $issues = [];
        $missing = [];
        $prices = [];
        $packets = 0;
        $amount = 0.0;

        foreach ($item['rows'] as $number => $row) {
            $rowPackets = $this->number($row['I'] ?? null);
            $rowPrice = $this->number($row['K'] ?? null);

            if ($rowPackets === null) {
                $missing[] = 'packets';
            } else {
                $packets += $rowPackets;
            }

            if ($rowPrice === null) {
                $missing[] = 'price';
            }

            if ($rowPackets === null || $rowPrice === null) {
                continue;
            }

            $prices[] = ['row' => $number, 'packets' => $rowPackets, 'price' => $rowPrice];

            if ($packet !== null) {
                $pieces = $rowPackets * $packet;
                $amount += $pieces * $rowPrice;
                $this->compare($issues, 'pieces_differ', $row['J'] ?? null, $pieces, $number);
                $this->compare($issues, 'amount_differs', $row['L'] ?? null, $pieces * $rowPrice, $number);
            }
        }

        if ($packet === null) {
            $missing[] = 'pieces_per_packet';
        }

        if ($perCarton === null) {
            $missing[] = 'pieces_per_carton';
        }

        if ($missing !== []) {
            $issues[] = $this->issue('missing_numbers', self::ERROR, ['fields' => array_values(array_unique($missing))], $firstRow);
        }

        $packetsKnown = ! in_array('packets', $missing, true);
        $boxesPerCarton = $packet !== null && $perCarton !== null && $packet > 0 ? $this->tidy(round($perCarton / $packet, 2)) : null;
        $cartons = $packetsKnown && $boxesPerCarton !== null && $boxesPerCarton > 0 ? (int) ceil(round($packets / $boxesPerCarton, 6)) : null;

        if ($cartons !== null) {
            $this->compare($issues, 'cartons_differ', $first['F'] ?? null, $cartons, $firstRow);
        }

        $distinctPrices = collect($prices)->pluck('price')->unique()->values();
        $pieces = $packet === null ? null : $packets * $packet;
        $unitCost = match (true) {
            $distinctPrices->count() === 1 => $distinctPrices->first(),
            $distinctPrices->count() > 1 && $pieces > 0 => round($amount / $pieces, 4),
            default => null,
        };

        if ($distinctPrices->count() > 1) {
            $issues[] = $this->issue('two_prices_averaged', self::WARNING, ['prices' => $distinctPrices->all(), 'average' => $unitCost], $firstRow);
        }

        $matchedItem = $this->matchItem($item['code'], $packet, $known['items'], $issues, $firstRow);
        $groups = $this->groups($this->text($first['C'] ?? null), $known['powers'], $issues, $firstRow);
        $colors = $this->colors($this->text($first['E'] ?? null), $known['colors'], $issues, $firstRow);

        $this->checkBreakdowns($issues, $firstRow, $packetsKnown ? $packets : null, $packet, $groups, $colors);

        return [
            'code' => $item['code'],
            'rows' => array_keys($item['rows']),
            'item' => $matchedItem,
            'packets' => $packetsKnown ? $this->tidy($packets) : null,
            'pieces_per_packet' => $packet,
            'boxes_per_carton' => $boxesPerCarton,
            'cartons' => $cartons,
            'carton_cbm' => null,
            'unit_cost' => $unitCost,
            'amount' => round($amount, 2),
            'prices' => $prices,
            'colors' => $colors,
            'groups' => $groups,
            'issues' => $issues,
        ];
    }

    /**
     * @param  list<Issue>  $issues
     * @param  list<array{power: int|float, packets: int, lens_power_id: ?int}>  $groups
     * @param  list<array{name: string, pieces: int, color_id: ?int}>  $colors
     */
    private function checkBreakdowns(array &$issues, int $row, int|float|null $packets, int|float|null $packet, array $groups, array $colors): void
    {
        if ($groups !== [] && $packets !== null) {
            $spread = array_sum(array_column($groups, 'packets'));

            if (abs($spread - $packets) > self::TOLERANCE) {
                $issues[] = $this->issue('groups_must_add_up', self::ERROR, ['spread' => $spread, 'packets' => $this->tidy($packets)], $row);
            }
        }

        if ($colors !== [] && $packet !== null) {
            $colorPieces = array_sum(array_column($colors, 'pieces'));

            if (abs($colorPieces - $packet) > self::TOLERANCE) {
                $issues[] = $this->issue('colors_must_fill_the_packet', self::ERROR, ['pieces' => $colorPieces, 'packet' => $packet], $row);
            }
        }
    }

    /**
     * The one item named as the code, in any letter case; none, or more than one, is reported, and so is an item whose
     * packet is not the file's.
     *
     * @param  Collection<string, EloquentCollection<int, Item>>  $items
     * @param  list<Issue>  $issues
     * @return array{id: int, name: string, specific_description_id: ?int}|null
     */
    private function matchItem(string $code, int|float|null $packet, Collection $items, array &$issues, int $row): ?array
    {
        $named = $items->get(mb_strtolower($code), new EloquentCollection);

        if ($named->count() !== 1) {
            $issues[] = $this->issue($named->isEmpty() ? 'item_not_found' : 'item_ambiguous', self::ERROR, ['code' => $code], $row);

            return null;
        }

        /** @var Item $item */
        $item = $named->first();

        if ($item->specificDescription === null) {
            $issues[] = $this->issue('item_not_found', self::ERROR, ['code' => $code], $row);

            return null;
        }

        if ($item->pieces_per_packet !== null && $packet !== null && (float) $item->pieces_per_packet !== (float) $packet) {
            $issues[] = $this->issue('packet_differs_from_item', self::ERROR, ['file' => $packet, 'item' => $item->pieces_per_packet], $row);
        }

        return ['id' => $item->id, 'name' => $item->name, 'specific_description_id' => $item->specificDescription->id];
    }

    /**
     * The powers of the line with their packets, each with the system's power of the same value.
     *
     * @param  EloquentCollection<int, LensPower>  $powers
     * @param  list<Issue>  $issues
     * @return list<array{power: int|float, packets: int, lens_power_id: ?int}>
     */
    private function groups(?string $text, EloquentCollection $powers, array &$issues, int $row, bool $template = false): array
    {
        if ($text === null) {
            return [];
        }

        if ($template) {
            $matches = $this->templateEntries($text, '/^([+-]?\d+(?:\.\d+)?)\s*[:：]\s*([1-9]\d*)$/u', 'powers_unreadable', $issues, $row);
        } else {
            preg_match_all(self::POWER, $text, $matches, PREG_SET_ORDER);
            $this->reportLeftover($text, self::POWER, 'powers_unreadable', $issues, $row);
        }

        return array_map(function (array $match) use ($powers, &$issues, $row, $template): array {
            $value = $this->tidy($template || str_contains($match[1], '.') ? (float) $match[1] : ((int) $match[1]) / 100);
            $power = $powers->first(fn (LensPower $power): bool => abs((float) $power->value - $value) < 0.001);

            if ($power === null) {
                $issues[] = $this->issue('lens_power_not_found', self::ERROR, ['power' => $value], $row);
            }

            return ['power' => $value, 'packets' => (int) $match[2], 'lens_power_id' => $power?->id];
        }, $matches);
    }

    /**
     * The colors of a packet of the line with their pieces, each with the system's color of the same name.
     *
     * @param  Collection<string, Color>  $colors
     * @param  list<Issue>  $issues
     * @return list<array{name: string, pieces: int, color_id: ?int}>
     */
    private function colors(?string $text, Collection $colors, array &$issues, int $row, bool $template = false): array
    {
        if ($text === null) {
            return [];
        }

        if ($template) {
            $matches = $this->templateEntries($text, '/^([^:：]+)\s*[:：]\s*([1-9]\d*)$/u', 'colors_unreadable', $issues, $row);
        } else {
            preg_match_all(self::COLOR, $text, $matches, PREG_SET_ORDER);
            $this->reportLeftover($text, self::COLOR, 'colors_unreadable', $issues, $row);
        }

        return array_map(function (array $match) use ($colors, &$issues, $row): array {
            $name = (string) preg_replace('/\s+/u', ' ', trim($match[1]));
            $color = $colors->get(mb_strtolower($name));

            if ($color === null) {
                $issues[] = $this->issue('color_not_found', self::ERROR, ['name' => $name], $row);
            }

            return ['name' => $name, 'pieces' => (int) $match[2], 'color_id' => $color?->id];
        }, $matches);
    }

    /**
     * @param  list<Issue>  $issues
     * @return list<array{string, string, string}>
     */
    private function templateEntries(string $text, string $pattern, string $issue, array &$issues, int $row): array
    {
        $matches = [];
        foreach (preg_split('~[/\r\n;；]+~u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (preg_match($pattern, $entry, $match)) {
                $matches[] = $match;
            } else {
                $issues[] = $this->issue($issue, self::ERROR, ['text' => $entry], $row);
            }
        }

        return $matches;
    }

    /**
     * Reports what is left of the text once everything read is taken out of it, so nothing written is lost unnoticed.
     *
     * @param  list<Issue>  $issues
     */
    private function reportLeftover(string $text, string $pattern, string $type, array &$issues, int $row): void
    {
        $leftover = trim(implode(' ', preg_split(self::SEPARATORS, (string) preg_replace($pattern, ' ', $text), flags: PREG_SPLIT_NO_EMPTY) ?: []));

        if ($leftover !== '') {
            $issues[] = $this->issue($type, self::ERROR, ['text' => $leftover], $row);
        }
    }

    /**
     * Warns when a number the file writes is not what it is made of.
     *
     * @param  list<Issue>  $issues
     */
    private function compare(array &$issues, string $type, mixed $written, int|float $read, int $row): void
    {
        $written = $this->number($written);

        if ($written !== null && abs($written - $read) > self::TOLERANCE) {
            $issues[] = $this->issue($type, self::WARNING, ['written' => $written, 'read' => $this->tidy(round($read, 2))], $row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{items: int, rows: int, packets: int|float|null, cartons: int|float|null, pieces: int|float|null, amount: float|null}
     */
    private function summary(array $lines, bool $requireEveryLine = false): array
    {
        $lines = collect($lines);
        $sum = function (Collection $values) use ($requireEveryLine): int|float|null {
            return $requireEveryLine && $values->containsStrict(null) ? null : $this->tidy($values->sum());
        };
        $pieces = $lines->map(fn (array $line): int|float|null => $line['packets'] === null || $line['pieces_per_packet'] === null
            ? null
            : $line['packets'] * $line['pieces_per_packet']);
        $amount = $sum($lines->pluck('amount'));

        return [
            'items' => $lines->count(),
            'rows' => $lines->sum(fn (array $line): int => count($line['rows'])),
            'packets' => $sum($lines->pluck('packets')),
            'cartons' => $sum($lines->pluck('cartons')),
            'pieces' => $sum($pieces),
            'amount' => $amount === null ? null : round($amount, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return Issue
     */
    private function issue(string $type, string $level, array $values = [], ?int $row = null): array
    {
        $words = collect($values)->map(fn (mixed $value, string $key): string => match (true) {
            $key === 'fields' => collect($value)->map(fn (string $field): string => __("invoices.excel.fields.{$field}"))->implode(__('invoices.excel.list_separator')),
            is_array($value) => "\u{2066}".collect($value)->map(fn (int|float $number): string => Number::format($number, maxPrecision: 4))->implode(' / ')."\u{2069}",
            is_int($value) || is_float($value) => Number::format($value, maxPrecision: 2),
            default => (string) $value,
        })->all();

        return ['type' => $type, 'level' => $level, 'row' => $row, 'values' => $values, 'message' => __("invoices.excel.{$type}", $words)];
    }

    private function text(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $this->tidy($value);
        }

        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }

    private function number(mixed $value): int|float|null
    {
        if (is_string($value) && is_numeric(trim($value))) {
            $value = (float) trim($value);
        }

        return (is_int($value) || is_float($value)) && is_finite($value) ? $this->tidy($value) : null;
    }

    /**
     * A whole number as an int, so 500.0 packets read as 500.
     */
    private function tidy(int|float $number): int|float
    {
        return is_float($number) && floor($number) === $number && abs($number) < PHP_INT_MAX ? (int) $number : $number;
    }
}
