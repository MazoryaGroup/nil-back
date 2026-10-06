<?php

namespace App\Filament\Resources\AccountingAuditLogResource\Pages;

use App\Filament\Resources\AccountingAuditLogResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountingAuditLog extends CreateRecord
{
    protected static string $resource = AccountingAuditLogResource::class;
}
