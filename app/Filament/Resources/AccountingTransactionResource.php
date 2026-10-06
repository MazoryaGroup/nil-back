<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingTransactionResource\Pages;
use App\Helpers\JalaliHelper;
use App\Models\AccountingTransaction;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountingTransactionResource extends Resource
{
    protected static ?string $model = AccountingTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'تراکنش‌های مالی';

    protected static ?string $modelLabel = 'تراکنش مالی';

    protected static ?string $pluralModelLabel = 'تراکنش‌های مالی';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('id')
                    ->label('شناسه')
                    ->disabled(),

                Forms\Components\Select::make('type')
                    ->label('نوع تراکنش')
                    ->options([
                        'income' => 'درآمد',
                        'expense' => 'هزینه',
                        'refund' => 'برگشت وجه',
                    ])
                    ->disabled(),

                Forms\Components\TextInput::make('amount')
                    ->label('مبلغ')
                    ->suffix('تومان')
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

                Forms\Components\TextInput::make('category')
                    ->label('دسته‌بندی')
                    ->disabled(),

                Forms\Components\Select::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'completed' => 'تکمیل شده',
                        'failed' => 'ناموفق',
                        'cancelled' => 'لغو شده',
                    ])
                    ->disabled(),

                Forms\Components\TextInput::make('client.name')
                    ->label('مشتری')
                    ->disabled(),

                Forms\Components\TextInput::make('booking_id')
                    ->label('شماره رزرو')
                    ->disabled(),

                Forms\Components\TextInput::make('payment_id')
                    ->label('شماره پرداخت')
                    ->disabled(),

                Forms\Components\TextInput::make('reference_number')
                    ->label('شماره مرجع')
                    ->disabled(),

                /*
                |--------------------------------------------------------------------------
                | تاریخ تراکنش - شمسی
                |--------------------------------------------------------------------------
                */

                JalaliDateTimePicker::make('transaction_date')
                    ->label('تاریخ تراکنش')
                    ->displayFormat('Y/m/d H:i')
                    ->seconds(false)
                    ->native(false)
                    ->disabled(),

                Forms\Components\TextInput::make('creator.name')
                    ->label('ثبت شده توسط')
                    ->disabled(),

                Forms\Components\Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(4)
                    ->columnSpanFull()
                    ->disabled(),

                Forms\Components\TextInput::make('offline_id')
                    ->label('Offline ID')
                    ->disabled(),

                /*
                |--------------------------------------------------------------------------
                | زمان همگام‌سازی - شمسی
                |--------------------------------------------------------------------------
                */

                JalaliDateTimePicker::make('synced_at')
                    ->label('زمان همگام‌سازی')
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
            ->defaultSort('transaction_date', 'desc')

            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | تاریخ تراکنش - نمایش شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('تاریخ')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime(
                                $state,
                                'Y/m/d H:i'
                            )
                            : '-'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'income' => 'درآمد',
                            'expense' => 'هزینه',
                            'refund' => 'برگشت وجه',
                            default => $state ?? '-',
                        }
                    ),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('مشتری')
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('booking_id')
                    ->label('رزرو')
                    ->formatStateUsing(
                        fn ($state) => $state ? '#' . $state : '-'
                    )
                    ->sortable(),

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

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('شماره مرجع')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'completed' => 'تکمیل شده',
                            'pending' => 'در انتظار',
                            'failed' => 'ناموفق',
                            'cancelled' => 'لغو شده',
                            default => $state ?? '-',
                        }
                    ),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('ثبت‌کننده')
                    ->placeholder('سیستم')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('offline_id')
                    ->label('Offline ID')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])

            ->filters([

                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع تراکنش')
                    ->options([
                        'income' => 'درآمد',
                        'expense' => 'هزینه',
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
                        'completed' => 'تکمیل شده',
                        'pending' => 'در انتظار',
                        'failed' => 'ناموفق',
                        'cancelled' => 'لغو شده',
                    ]),

            ])

            ->actions([

                Tables\Actions\ViewAction::make()
                    ->label('مشاهده'),

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
            'index' => Pages\ListAccountingTransactions::route('/'),
            'view' => Pages\ViewAccountingTransaction::route('/{record}'),
        ];
    }
}
