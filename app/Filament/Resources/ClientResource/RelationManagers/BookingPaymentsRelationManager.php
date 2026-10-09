<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\Payment;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BookingPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookingPayments';

    protected static ?string $title = 'تاریخچه پرداخت‌های مشتری';

    protected static ?string $recordTitleAttribute = 'id';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('شناسه تراکنش')
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking_id')
                    ->label('شماره رزرو')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع تراکنش')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state) => match ($state) {
                            'deposit' => 'بیعانه',
                            'payment' => 'پرداخت',
                            'refund' => 'بازگشت وجه',
                            default => $state ?? '-',
                        }
                    ),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('روش پرداخت')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state) => match ($state) {
                            'online' => 'آنلاین',
                            'pos' => 'کارت‌خوان',
                            'cash' => 'نقدی',
                            'card_to_card' => 'کارت به کارت',
                            default => $state ?? '-',
                        }
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت تراکنش')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state) => match ($state) {
                            'pending' => 'در انتظار',
                            'success', 'successful', 'completed', 'paid' => 'موفق',
                            'failed' => 'ناموفق',
                            'cancelled' => 'لغوشده',
                            'refunded' => 'برگشت داده‌شده',
                            default => $state ?? '-',
                        }
                    )
                    ->color(
                        fn (?string $state) => match ($state) {
                            'success', 'successful', 'completed', 'paid' => 'success',
                            'failed', 'cancelled' => 'danger',
                            'pending' => 'warning',
                            'refunded' => 'info',
                            default => 'gray',
                        }
                    ),

                Tables\Columns\TextColumn::make('gateway')
                    ->label('درگاه')
                    ->default('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('شماره پیگیری')
                    ->default('-')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('شناسه پرداخت')
                    ->default('-')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('refunded_payment_id')
                    ->label('شناسه پرداخت مرجع')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('زمان پرداخت')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(),

            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
