<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            /** @var ExpenseService $expenseService */
            $expenseService = app(ExpenseService::class);

            /** @var Expense $expense */
            $expense = $expenseService->createExpense(
                expenseCategoryId: (int) $data['expense_category_id'],
                amount: (float) $data['amount'],
                paymentMethod: $data['payment_method'],
                referenceNumber: $data['reference_number'] ?? null,
                description: $data['description'] ?? null,
                createdBy: auth()->id(),
                offlineId: null,
            );

            return $expense;

        } catch (Throwable $e) {

            Notification::make()
                ->title('خطا در ثبت هزینه')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'هزینه با موفقیت ثبت شد';
    }

    protected function getRedirectUrl(): string
    {
        return ExpenseResource::getUrl('index');
    }
}
