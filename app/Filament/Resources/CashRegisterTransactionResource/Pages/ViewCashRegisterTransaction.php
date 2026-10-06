<?php

namespace App\Filament\Resources\CashRegisterTransactionResource\Pages;

use App\Filament\Resources\CashRegisterTransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCashRegisterTransaction extends ViewRecord
{
    protected static string $resource = CashRegisterTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
