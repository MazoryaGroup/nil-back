<?php

namespace App\Filament\Resources\AccountingTransactionResource\Pages;

use App\Filament\Resources\AccountingTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccountingTransaction extends EditRecord
{
    protected static string $resource = AccountingTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
