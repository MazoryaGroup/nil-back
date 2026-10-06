<?php


namespace App\Filament\Resources\AccountingTransactionResource\Pages;

use App\Filament\Resources\AccountingTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountingTransaction extends ViewRecord
{
    protected static string $resource = AccountingTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
