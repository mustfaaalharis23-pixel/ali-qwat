<?php

namespace App\Filament\Resources\Containers\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Tables\Columns\IdColumn;
use App\Invoices\SplitPurchaseInvoice;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemGroup;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View as IlluminateView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * The purchase invoices (shipments) of a container, shown on its page, with each invoice's share of the transport cost.
 * Purchase invoices that are in no container are added here, and taken out of the container here.
 * After every change the container's total cards are told to show their numbers again.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/resources/purchase_invoice.plural_label');
    }

    /**
     * Invoices are added and taken out from the container's details page too, so the table is not read only there.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Invoice $record): string => "#{$record->id} · {$record->name} · {$record->supplier?->name_ar}")
            ->modelLabel(__('filament/resources/purchase_invoice.label'))
            ->pluralModelLabel(__('filament/resources/purchase_invoice.plural_label'))
            ->description(__('filament/resources/container.invoices_description'))
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id')
                    ->resource(PurchaseInvoiceResource::class),
                TextColumn::make('name')
                    ->label(__('filament/resources/invoice.fields.name'))
                    ->searchable(),
                TextColumn::make('supplier.name_ar')
                    ->label(__('filament/resources/invoice.fields.supplier_id')),
                TextColumn::make('date')
                    ->label(__('filament/resources/invoice.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('cbm')
                    ->label(__('filament/resources/invoice.fields.cbm'))
                    ->numeric(maxDecimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('cbm_price')
                    ->label(__('filament/resources/invoice.fields.cbm_price'))
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' $'),
                TextColumn::make('transport_cost_usd')
                    ->label(__('filament/resources/invoice.fields.transport_cost'))
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' $'),
                TextColumn::make('status')
                    ->label(__('filament/resources/invoice.fields.status'))
                    ->badge(),
            ])
            ->recordUrl(fn (Invoice $record): string => PurchaseInvoiceResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                AssociateAction::make()
                    ->label(__('filament/resources/container.actions.add_invoices'))
                    ->modalHeading(__('filament/resources/container.actions.add_invoices'))
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->purchases()->whereNull('container_id')->with('supplier'))
                    ->authorize(fn (): bool => $this->canChangeInvoices())
                    ->after(fn () => $this->dispatch('container-invoices-changed')),
                $this->addPartOfInvoiceAction(),
            ])
            ->recordActions([
                DissociateAction::make()
                    ->label(__('filament/resources/container.actions.take_invoice_out'))
                    ->modalHeading(__('filament/resources/container.actions.take_invoice_out'))
                    ->authorize(fn (): bool => $this->canChangeInvoices())
                    ->after(fn () => $this->dispatch('container-invoices-changed')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make()
                        ->label(__('filament/resources/container.actions.take_invoices_out'))
                        ->modalHeading(__('filament/resources/container.actions.take_invoices_out'))
                        ->authorize(fn (): bool => $this->canChangeInvoices())
                        ->after(fn () => $this->dispatch('container-invoices-changed')),
                ]),
            ]);
    }

    /**
     * Ships part of a purchase invoice in this container: the user picks an invoice and writes the cartons of each line that go,
     * and those cartons are split off into a new invoice in the container (see SplitPurchaseInvoice). A refused split is shown as a notification.
     */
    private function addPartOfInvoiceAction(): Action
    {
        return Action::make('addPartOfInvoice')
            ->label(__('filament/resources/container.actions.add_part_of_invoice.label'))
            ->modalHeading(__('filament/resources/container.actions.add_part_of_invoice.label'))
            ->modalDescription(__('filament/resources/container.actions.add_part_of_invoice.description'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->color('gray')
            ->authorize(fn (): bool => $this->canChangeInvoices())
            ->schema([
                Select::make('invoice_id')
                    ->label(__('filament/resources/container.actions.add_part_of_invoice.invoice'))
                    ->options(fn (): array => $this->invoicesThatCanBeSplit())
                    ->searchable()
                    ->required()
                    ->live(),
                Group::make()
                    ->schema(fn (Get $get): array => $this->cartonFieldsOf($get('invoice_id'))),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    $part = (new SplitPurchaseInvoice)->handle(
                        Invoice::query()->findOrFail($data['invoice_id']),
                        $this->container(),
                        array_map('intval', $data['cartons'] ?? []),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first())
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('filament/resources/container.actions.add_part_of_invoice.done', ['name' => $part->name]))
                    ->send();

                $this->dispatch('container-invoices-changed');
            });
    }

    /**
     * The purchase invoices that can be split: in no container and not confirmed yet, newest first.
     *
     * @return array<int, string>
     */
    private function invoicesThatCanBeSplit(): array
    {
        return Invoice::query()
            ->purchases()
            ->whereNull('container_id')
            ->where('status', '!=', InvoiceStatus::Confirmed)
            ->with('supplier')
            ->orderByDesc('date')
            ->get()
            ->mapWithKeys(fn (Invoice $invoice): array => [$invoice->id => "#{$invoice->id} · {$invoice->name} · {$invoice->supplier?->name_ar}"])
            ->all();
    }

    /**
     * One card for each line of the chosen invoice, so the right line is not mistaken: the item's large image and name,
     * how the line is packed, and its cartons field (from zero to the line's cartons; an empty field sends no cartons).
     * As the cartons are typed, the card shows what goes into the container and what stays on the original.
     *
     * @return array<int, Section>
     */
    private function cartonFieldsOf(mixed $invoiceId): array
    {
        if (blank($invoiceId)) {
            return [];
        }

        return InvoiceItem::query()
            ->where('invoice_id', $invoiceId)
            ->with(['specificDescription', 'item', 'groups.lensPower', 'colors.color'])
            ->get()
            ->map(fn (InvoiceItem $line): Section => Section::make()
                ->compact()
                ->schema([
                    Flex::make([
                        View::make('filament.containers.split-line-image')
                            ->viewData([
                                'url' => filled($line->item?->image) ? Storage::disk('public')->url($line->item->image) : null,
                                'alt' => (string) $line->specificDescription?->name,
                            ])
                            ->grow(false),
                        Group::make([
                            Text::make((string) $line->specificDescription?->name)
                                ->size(TextSize::Large)
                                ->weight(FontWeight::Bold)
                                ->color('neutral'),
                            View::make('filament.forms.components.line-facts')
                                ->viewData(['facts' => $this->packingFactsOf($line)]),
                            TextInput::make("cartons.{$line->id}")
                                ->label(__('filament/resources/container.actions.add_part_of_invoice.cartons'))
                                ->suffix(__('filament/resources/container.actions.add_part_of_invoice.cartons_hint', ['count' => (int) $line->cartons_count]))
                                ->integer()
                                ->minValue(0)
                                ->maxValue((int) $line->cartons_count)
                                ->placeholder('0')
                                ->live(debounce: 400)
                                ->belowContent(fn (mixed $state): IlluminateView => $this->splitPreviewOf($line, $state)),
                        ]),
                    ])->from('sm'),
                ]))
            ->all();
    }

    /**
     * How the line is packed, as chips under its name: its groups (power and packets) and the colors of a packet when it has any,
     * its cartons, the packets in a carton, the pieces in a packet, and its pieces. The groups and the colors come first, so two
     * lines of the same item are told apart.
     *
     * @return list<array{text: string, tooltip: null, color: string, icon: Heroicon}>
     */
    private function packingFactsOf(InvoiceItem $line): array
    {
        $facts = [
            ['cartons', $line->cartons_count, 'info', Heroicon::OutlinedCube],
            ['packets_per_carton', $line->boxes_per_carton, 'gray', Heroicon::OutlinedSquares2x2],
            ['pieces_per_packet', $line->pieces_per_packet, 'gray', Heroicon::OutlinedSquaresPlus],
            ['pieces', $line->quantity, 'gray', Heroicon::OutlinedHashtag],
        ];

        $chips = array_map(fn (array $fact): array => [
            'text' => __("filament/resources/container.actions.add_part_of_invoice.line.{$fact[0]}", ['value' => $this->number($fact[1])]),
            'tooltip' => null,
            'color' => $fact[2],
            'icon' => $fact[3],
        ], $facts);

        $groups = $line->groups
            ->map(fn (InvoiceItemGroup $group): array => [
                'text' => $group->description(),
                'tooltip' => null,
                'color' => 'primary',
                'icon' => Heroicon::OutlinedEye,
            ])
            ->all();

        $colors = $line->colorsDescription() === null ? [] : [[
            'text' => $line->colorsDescription(),
            'tooltip' => null,
            'color' => 'gray',
            'icon' => Heroicon::OutlinedSwatch,
        ]];

        return [...$groups, ...$colors, ...$chips];
    }

    /**
     * Under the cartons field, as they are typed: what goes into the container and what stays on the original,
     * in cartons, packets, and units, worked out the way the split works them out. A red chip when the cartons are more than the line has.
     */
    private function splitPreviewOf(InvoiceItem $line, mixed $cartons): IlluminateView
    {
        $key = 'filament/resources/container.actions.add_part_of_invoice.preview';
        $cartons = (int) $cartons;
        $lineCartons = (int) $line->cartons_count;

        if ($cartons > $lineCartons) {
            $facts = [[
                'text' => __("{$key}.too_many", ['count' => $this->number($lineCartons)]),
                'tooltip' => null,
                'color' => 'danger',
                'icon' => Heroicon::OutlinedExclamationTriangle,
            ]];
        } elseif ($cartons <= 0) {
            $facts = [[
                'text' => __("{$key}.nothing"),
                'tooltip' => null,
                'color' => 'gray',
                'icon' => Heroicon::OutlinedArchiveBox,
            ]];
        } else {
            $packets = (new SplitPurchaseInvoice)->packetsIn($line, $cartons);
            $amounts = fn (float $cartons, float $packets): array => [
                'cartons' => $this->number($cartons),
                'packets' => $this->number($packets),
                'pieces' => $this->number($packets * (float) $line->pieces_per_packet),
            ];

            $facts = $cartons === $lineCartons
                ? [[
                    'text' => __("{$key}.all", $amounts($cartons, $packets)),
                    'tooltip' => null,
                    'color' => 'success',
                    'icon' => Heroicon::OutlinedTruck,
                ]]
                : [[
                    'text' => __("{$key}.goes", $amounts($cartons, $packets)),
                    'tooltip' => null,
                    'color' => 'warning',
                    'icon' => Heroicon::OutlinedTruck,
                ], [
                    'text' => __("{$key}.stays", $amounts($lineCartons - $cartons, (float) $line->boxes_count - $packets)),
                    'tooltip' => null,
                    'color' => 'gray',
                    'icon' => Heroicon::OutlinedArchiveBox,
                ]];
        }

        return view('filament.forms.components.line-facts', ['facts' => $facts]);
    }

    /**
     * A number written the way the panel writes numbers, with no decimals it does not need.
     */
    private function number(float|int|string|null $value): string
    {
        return Number::format((float) $value, maxPrecision: 2);
    }

    /**
     * Adding invoices to the container or taking them out of it edits the container, so it needs the container's edit permission.
     * A deleted container gets no changes.
     */
    private function canChangeInvoices(): bool
    {
        $container = $this->container();

        return ! $container->trashed() && (auth()->user()?->can('update', $container) ?? false);
    }

    private function container(): Container
    {
        /** @var Container */
        return $this->getOwnerRecord();
    }
}
