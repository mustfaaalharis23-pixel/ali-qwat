<?php

namespace App\Filament\Resources\GeneralInvoices\Pages;

use App\Filament\Resources\GeneralInvoices\GeneralInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGeneralInvoice extends CreateRecord
{
    protected static string $resource = GeneralInvoiceResource::class;

    /**
     * The user who creates the invoice is saved as its creator.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
