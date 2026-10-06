<?php

namespace App\Filament\Resources\AccountingTransactionResource\Pages;

use App\Filament\Resources\AccountingTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountingTransactions extends ListRecords
{
    protected static string $resource = AccountingTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
