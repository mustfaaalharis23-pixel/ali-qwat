<?php

namespace App\Filament\Reports;

use App\Filament\Forms\Components\DateInput;
use App\Metrics\Currency;
use App\Metrics\Period;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The template of every report: a page with a period form (a single day or a from-until period), a fetch button,
 * an Excel download button, and a table of the fetched rows. A report gives its uriKey, its label, its columns, and its rows.
 * The table stays empty until the fetch button is pressed, and the Excel file holds what was fetched.
 *
 * @property-read Schema $form
 */
abstract class Report extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    /**
     * Higher than the Lenses group, so the Reports group comes last in the sidebar.
     */
    protected static ?int $navigationSort = 200;

    /**
     * The state of the period form.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * The first day of the fetched period, as Y-m-d, or null before anything is fetched.
     */
    public ?string $fetchedFrom = null;

    /**
     * The last day of the fetched period, as Y-m-d, or null before anything is fetched.
     */
    public ?string $fetchedUntil = null;

    /**
     * The rows of the fetched period, kept for one request so the table and the totals do not query twice.
     *
     * @var list<array<string, mixed>>|null
     */
    protected ?array $cachedRows = null;

    /**
     * The unique key of the report, in kebab case, such as sales. It builds the address and the Excel file name.
     */
    abstract public static function uriKey(): string;

    /**
     * The name of the report, shown as the page title and the sidebar link.
     */
    abstract public static function label(): string;

    /**
     * The columns of the report table. Their labels are the headings of the Excel file.
     *
     * @return list<Column>
     */
    abstract public function columns(): array;

    /**
     * The rows of the report inside the period, each keyed by column name.
     *
     * @return list<array<string, mixed>>
     */
    abstract public function rows(Period $period): array;

    /**
     * The totals shown in colored cards under the table and at the end of the Excel file. A report has none unless it gives them.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, value: float, color: string, currency?: Currency}>
     */
    public function totals(array $rows): array
    {
        return [];
    }

    /**
     * The report's own fields, shown after the dates in the period form, such as a sort choice. Their state is in $this->data.
     * A report has none unless it gives them.
     *
     * @return list<Component>
     */
    public function filterFields(): array
    {
        return [];
    }

    /**
     * The actions on each row of the report table. Their $record is the row array. A report has none unless it gives them.
     *
     * @return list<Action>
     */
    public function recordActions(): array
    {
        return [];
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'reports/'.static::uriKey();
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.reports');
    }

    public static function getNavigationLabel(): string
    {
        return static::label();
    }

    public function getTitle(): string|Htmlable
    {
        return static::label();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * The period form: a single day by default, today, or a from-until period.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Flex::make([
                            ToggleButtons::make('mode')
                                ->label(__('filament/reports.fields.mode'))
                                ->options([
                                    'day' => __('filament/reports.modes.day'),
                                    'range' => __('filament/reports.modes.range'),
                                ])
                                ->default('day')
                                ->grouped()
                                ->live()
                                ->required()
                                ->grow(false),
                            DateInput::make('date')
                                ->label(__('filament/reports.fields.date'))
                                ->defaultToday()
                                ->visible(fn (Get $get): bool => $get('mode') === 'day')
                                ->required()
                                ->extraFieldWrapperAttributes(['style' => 'min-width: 14rem'])
                                ->grow(false),
                            DateInput::make('from')
                                ->label(__('filament/reports.fields.from'))
                                ->visible(fn (Get $get): bool => $get('mode') === 'range')
                                ->required()
                                ->extraFieldWrapperAttributes(['style' => 'min-width: 14rem'])
                                ->grow(false),
                            DateInput::make('until')
                                ->label(__('filament/reports.fields.until'))
                                ->visible(fn (Get $get): bool => $get('mode') === 'range')
                                ->afterOrEqual('from')
                                ->required()
                                ->extraFieldWrapperAttributes(['style' => 'min-width: 14rem'])
                                ->grow(false),
                            ...$this->filterFields(),
                            Actions::make([
                                Action::make('fetch')
                                    ->label(__('filament/reports.actions.fetch'))
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->submit('fetch'),
                                Action::make('downloadExcel')
                                    ->label(__('filament/reports.actions.download_excel'))
                                    ->icon(Heroicon::OutlinedArrowDownTray)
                                    ->color('success')
                                    ->outlined()
                                    ->disabled(fn (): bool => ! $this->hasFetched())
                                    ->action(fn (): StreamedResponse => $this->downloadExcel()),
                            ])
                                ->alignEnd(),
                        ])
                            ->from('md')
                            ->verticallyAlignEnd(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->livewireSubmitHandler('fetch'),
                EmbeddedTable::make(),
                View::make('filament.reports.totals')
                    ->viewData(fn (): array => ['totals' => $this->fetchedTotals()])
                    ->visible(fn (): bool => $this->fetchedTotals() !== []),
            ]);
    }

    /**
     * Validates the period form and fetches its period into the table.
     */
    public function fetch(): void
    {
        $data = $this->form->getState();

        [$this->fetchedFrom, $this->fetchedUntil] = $data['mode'] === 'day'
            ? [$data['date'], $data['date']]
            : [$data['from'], $data['until']];

        $this->cachedRows = null;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect($this->fetchedRows())
                ->mapWithKeys(fn (array $row, int $index): array => [$index + 1 => $row])
                ->all())
            ->columns($this->columns())
            ->recordActions($this->recordActions())
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedDocumentChartBar)
            ->emptyStateHeading(fn (): string => $this->hasFetched()
                ? __('filament/reports.empty.no_data')
                : __('filament/reports.empty.not_fetched'));
    }

    /**
     * Whether a period has been fetched.
     */
    protected function hasFetched(): bool
    {
        return $this->fetchedFrom !== null && $this->fetchedUntil !== null;
    }

    /**
     * The rows of the fetched period, from the first second of its first day to the last second of its last day.
     * Nothing before a period is fetched.
     *
     * @return list<array<string, mixed>>
     */
    protected function fetchedRows(): array
    {
        if (! $this->hasFetched()) {
            return [];
        }

        return $this->cachedRows ??= $this->rows($this->fetchedPeriod());
    }

    /**
     * The fetched period, from the first second of its first day to the last second of its last day.
     * Only call it after a period is fetched.
     */
    protected function fetchedPeriod(): Period
    {
        return new Period(
            CarbonImmutable::parse($this->fetchedFrom)->startOfDay(),
            CarbonImmutable::parse($this->fetchedUntil)->endOfDay(),
        );
    }

    /**
     * The totals of the fetched rows. Nothing when there are no rows.
     *
     * @return list<array{label: string, value: float, color: string, currency?: Currency}>
     */
    protected function fetchedTotals(): array
    {
        $rows = $this->fetchedRows();

        return $rows === [] ? [] : $this->totals($rows);
    }

    /**
     * Downloads the fetched rows as an Excel file: the column labels, then one line per row.
     * In Arabic the sheet reads from right to left.
     */
    protected function downloadExcel(): StreamedResponse
    {
        $columns = $this->getTable()->getColumns();
        $rows = $this->fetchedRows();
        $totals = $this->fetchedTotals();
        $isRightToLeft = __('filament-panels::layout.direction') === 'rtl';

        return response()->streamDownload(function () use ($columns, $rows, $totals, $isRightToLeft): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setSheetView((new SheetView)->setRightToLeft($isRightToLeft));

            $writer->addRow(Row::fromValues(array_values(array_map(
                fn (Column $column): string => (string) $column->getLabel(),
                $columns,
            ))));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_values(array_map(
                    fn (Column $column): mixed => $row[$column->getName()] ?? null,
                    $columns,
                ))));
            }

            if ($totals !== []) {
                $writer->addRow(Row::fromValues([]));

                foreach ($totals as $total) {
                    $writer->addRow(Row::fromValues([$total['label'], $total['value']]));
                }
            }

            $writer->close();
        }, static::uriKey()."_{$this->fetchedFrom}_{$this->fetchedUntil}.xlsx");
    }
}
