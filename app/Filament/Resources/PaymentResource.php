<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Helpers\JalaliHelper;
use App\Models\Payment;
use App\Services\RefundService;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'پرداخت‌ها';

    protected static ?string $modelLabel = 'پرداخت';

    protected static ?string $pluralModelLabel = 'پرداخت‌ها';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('id')
                    ->label('شناسه پرداخت')
                    ->disabled(),

                Forms\Components\TextInput::make('booking_id')
                    ->label('شناسه رزرو')
                    ->disabled(),

                Forms\Components\TextInput::make('client.name')
                    ->label('مشتری')
                    ->disabled(),

                Forms\Components\TextInput::make('client.phone')
                    ->label('شماره موبایل مشتری')
                    ->disabled(),

                Forms\Components\TextInput::make('amount')
                    ->label('مبلغ')
                    ->suffix('تومان')
                    ->disabled(),

                Forms\Components\Select::make('type')
                    ->label('نوع پرداخت')
                    ->options([
                        'deposit' => 'بیعانه',
                        'remaining' => 'تسویه باقی‌مانده',
                        'refund' => 'برگشت وجه',
                    ])
                    ->disabled(),

                Forms\Components\Select::make('payment_method')
                    ->label('روش پرداخت')
                    ->options([
                        'online' => 'آنلاین',
                        'cash' => 'نقدی',
                        'pos' => 'کارتخوان',
                        'bank_transfer' => 'انتقال بانکی',
                        'other' => 'سایر',
                    ])
                    ->disabled(),

                Forms\Components\Select::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار پرداخت',
                        'paid' => 'پرداخت شده',
                        'failed' => 'ناموفق',
                        'refunded' => 'برگشت داده شده',
                    ])
                    ->disabled(),

                Forms\Components\TextInput::make('gateway')
                    ->label('درگاه')
                    ->disabled(),

                Forms\Components\TextInput::make('transaction_id')
                    ->label('Transaction ID')
                    ->disabled(),

                Forms\Components\TextInput::make('reference_number')
                    ->label('شماره مرجع')
                    ->disabled(),

                Forms\Components\TextInput::make('authority')
                    ->label('Authority')
                    ->disabled(),

                Forms\Components\TextInput::make('offline_id')
                    ->label('Offline ID')
                    ->disabled(),

                Forms\Components\TextInput::make('refunded_payment_id')
                    ->label('پرداخت اصلی Refund')
                    ->disabled(),

                /*
                |--------------------------------------------------------------------------
                | زمان پرداخت - شمسی
                |--------------------------------------------------------------------------
                */

                JalaliDateTimePicker::make('paid_at')
                    ->label('زمان پرداخت')
                    ->displayFormat('Y/m/d H:i')
                    ->seconds(false)
                    ->native(false)
                    ->disabled(),

            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')

            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | تاریخ پرداخت - نمایش شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('تاریخ پرداخت')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime(
                                $state,
                                'Y/m/d H:i'
                            )
                            : '-'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('مشتری')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('booking_id')
                    ->label('رزرو')
                    ->formatStateUsing(
                        fn ($state) => $state ? '#' . $state : '-'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'deposit' => 'بیعانه',
                            'remaining' => 'تسویه',
                            'refund' => 'برگشت وجه',
                            default => $state ?? '-',
                        }
                    ),

                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('روش پرداخت')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'online' => 'آنلاین',
                            'cash' => 'نقدی',
                            'pos' => 'کارتخوان',
                            'bank_transfer' => 'انتقال بانکی',
                            'other' => 'سایر',
                            default => $state ?? '-',
                        }
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'pending' => 'در انتظار',
                            'paid' => 'پرداخت شده',
                            'failed' => 'ناموفق',
                            'refunded' => 'برگشت داده شده',
                            default => $state ?? '-',
                        }
                    ),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('شماره مرجع')
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('gateway')
                    ->label('درگاه')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('refunded_payment_id')
                    ->label('پرداخت اصلی')
                    ->formatStateUsing(
                        fn ($state) => $state ? '#' . $state : '-'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('offline_id')
                    ->label('Offline ID')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])

            ->filters([

                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع پرداخت')
                    ->options([
                        'deposit' => 'بیعانه',
                        'remaining' => 'تسویه',
                        'refund' => 'برگشت وجه',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('روش پرداخت')
                    ->options([
                        'online' => 'آنلاین',
                        'cash' => 'نقدی',
                        'pos' => 'کارتخوان',
                        'bank_transfer' => 'انتقال بانکی',
                        'other' => 'سایر',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'paid' => 'پرداخت شده',
                        'failed' => 'ناموفق',
                        'refunded' => 'برگشت داده شده',
                    ]),

            ])

            ->actions([

                Tables\Actions\ViewAction::make()
                    ->label('مشاهده'),

                Tables\Actions\Action::make('refund')
                    ->label('برگشت وجه')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->modalHeading('برگشت وجه')
                    ->modalDescription(
                        'مبلغ و روش برگشت وجه را وارد کنید. این عملیات در حسابداری ثبت خواهد شد.'
                    )

                    ->visible(function (Payment $record): bool {

                        if (
                            !in_array(
                                $record->type,
                                ['deposit', 'remaining'],
                                true
                            )
                        ) {
                            return false;
                        }

                        if ($record->status !== 'paid') {
                            return false;
                        }

                        $alreadyRefunded = (float) $record
                            ->refunds()
                            ->whereIn('status', ['paid', 'refunded'])
                            ->sum('amount');

                        return $alreadyRefunded < (float) $record->amount;
                    })

                    ->form(function (Payment $record): array {

                        $alreadyRefunded = (float) $record
                            ->refunds()
                            ->whereIn('status', ['paid', 'refunded'])
                            ->sum('amount');

                        $refundableAmount = max(
                            0,
                            (float) $record->amount - $alreadyRefunded
                        );

                        return [

                            Forms\Components\Placeholder::make(
                                'original_payment_amount'
                            )
                                ->label('مبلغ پرداخت اصلی')
                                ->content(
                                    number_format(
                                        (float) $record->amount
                                    ) . ' تومان'
                                ),

                            Forms\Components\Placeholder::make(
                                'already_refunded'
                            )
                                ->label('برگشت داده شده')
                                ->content(
                                    number_format(
                                        $alreadyRefunded
                                    ) . ' تومان'
                                ),

                            Forms\Components\Placeholder::make(
                                'refundable_amount'
                            )
                                ->label('قابل برگشت')
                                ->content(
                                    number_format(
                                        $refundableAmount
                                    ) . ' تومان'
                                ),

                            Forms\Components\TextInput::make('amount')
                                ->label('مبلغ برگشت وجه')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue($refundableAmount)
                                ->suffix('تومان')
                                ->required(),

                            Forms\Components\Select::make(
                                'payment_method'
                            )
                                ->label('روش برگشت وجه')
                                ->options([
                                    'online' => 'آنلاین',
                                    'cash' => 'نقدی',
                                    'pos' => 'کارتخوان',
                                    'bank_transfer' => 'انتقال بانکی',
                                    'other' => 'سایر',
                                ])
                                ->required(),

                            Forms\Components\TextInput::make(
                                'reference_number'
                            )
                                ->label('شماره مرجع')
                                ->maxLength(255),

                            Forms\Components\Textarea::make(
                                'description'
                            )
                                ->label('توضیحات')
                                ->rows(3),

                        ];
                    })

                    ->action(function (
                        Payment $record,
                        array $data
                    ): void {

                        try {

                            app(RefundService::class)
                                ->createRefund(
                                    payment: $record,
                                    amount: (float) $data['amount'],
                                    paymentMethod:
                                    $data['payment_method'],
                                    referenceNumber:
                                    $data['reference_number']
                                    ?? null,
                                    description:
                                    $data['description']
                                    ?? null,
                                    createdBy: auth()->id(),
                                    offlineId: null,
                                );

                            Notification::make()
                                ->title(
                                    'برگشت وجه با موفقیت ثبت شد'
                                )
                                ->success()
                                ->send();

                        } catch (Throwable $e) {

                            Notification::make()
                                ->title('خطا در برگشت وجه')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

            ])

            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
