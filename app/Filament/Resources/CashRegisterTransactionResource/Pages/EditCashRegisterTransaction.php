<?php

namespace App\Filament\Resources\CashRegisterTransactionResource\Pages;

use App\Filament\Resources\CashRegisterTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCashRegisterTransaction extends EditRecord
{
    protected static string $resource = CashRegisterTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
