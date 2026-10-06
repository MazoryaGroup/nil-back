<?php

namespace App\Filament\Resources\AccountingAuditLogResource\Pages;

use App\Filament\Resources\AccountingAuditLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountingAuditLogs extends ListRecords
{
    protected static string $resource = AccountingAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
