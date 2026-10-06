<?php

namespace App\Filament\Resources\AccountingAuditLogResource\Pages;

use App\Filament\Resources\AccountingAuditLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountingAuditLog extends ViewRecord
{
    protected static string $resource = AccountingAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
