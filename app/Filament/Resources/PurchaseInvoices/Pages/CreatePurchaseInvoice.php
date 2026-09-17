<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\GeneralDescription;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    /**
     * The page uses the full width, so the lines table has room for wide fields.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * A purchase invoice is always under the purchases description, of type output, and the user who creates it is its creator.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $purchases = GeneralDescription::purchases();

        $data['general_description_id'] = $purchases->id;
        $data['type'] = $purchases->type;
        $data['user_id'] = auth()->id();

        return $data;
    }
}
