<?php

namespace App\Services;

use App\Models\AccountingAuditLog;

class AccountingAuditService
{
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?int $bookingId = null,
        ?int $clientId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): AccountingAuditLog {

        return AccountingAuditLog::create([
            'user_id' => $userId,

            'action' => $action,

            'entity_type' => $entityType,

            'entity_id' => $entityId,

            'booking_id' => $bookingId,

            'client_id' => $clientId,

            'description' => $description,

            'old_values' => $oldValues,

            'new_values' => $newValues,

            'ip_address' => request()?->ip(),

            'user_agent' => request()?->userAgent(),

            'created_at' => now(),
        ]);
    }
}
