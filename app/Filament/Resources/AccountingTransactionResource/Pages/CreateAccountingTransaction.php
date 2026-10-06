<?php

namespace App\Filament\Resources\AccountingTransactionResource\Pages;

use App\Filament\Resources\AccountingTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountingTransaction extends CreateRecord
{
    protected static string $resource = AccountingTransactionResource::class;
}
