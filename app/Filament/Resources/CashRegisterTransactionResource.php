<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashRegisterTransactionResource\Pages;
use App\Helpers\JalaliHelper;
use App\Models\CashRegisterTransaction;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CashRegisterTransactionResource extends Resource
{
    protected static ?string $model = CashRegisterTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'گردش صندوق';

    protected static ?string $modelLabel = 'تراکنش صندوق';

    protected static ?string $pluralModelLabel = 'گردش صندوق';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('id')
                    ->label('شناسه')
                    ->disabled(),

                Forms\Components\TextInput::make('cash_register_id')
                    ->label('شماره صندوق')
                    ->disabled(),

                Forms\Components\Select::make('type')
                    ->label('نوع تراکنش')
                    ->options([
                        'income' => 'ورودی',
                        'expense' => 'هزینه / خروجی',
                        'refund' => 'برگشت وجه',
                        'cash_in' => 'ورود دستی وجه',
                        'cash_out' => 'خروج دستی وجه',
                        'adjustment' => 'اصلاح صندوق',
                    ])
                    ->disabled(),

                Forms\Components\TextInput::make('amount')
                    ->label('مبلغ')
                    ->suffix('تومان')
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

                Forms\Components\TextInput::make('payment_id')
                    ->label('شناسه پرداخت')
                    ->disabled(),

                Forms\Components\TextInput::make('expense_id')
                    ->label('شناسه هزینه')
                    ->disabled(),

                Forms\Components\TextInput::make('accounting_transaction_id')
                    ->label('شناسه تراکنش حسابداری')
                    ->disabled(),

                Forms\Components\TextInput::make('creator.name')
                    ->label('ثبت‌کننده')
                    ->disabled(),

                Forms\Components\TextInput::make('offline_id')
                    ->label('Offline ID')
                    ->disabled(),

                Forms\Components\Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(4)
                    ->columnSpanFull()
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

                Tables\Columns\TextColumn::make('cash_register_id')
                    ->label('صندوق')
                    ->formatStateUsing(
                        fn ($state) => '#' . $state
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'income' => 'ورودی',
                            'expense' => 'هزینه',
                            'refund' => 'برگشت وجه',
                            'cash_in' => 'ورود دستی',
                            'cash_out' => 'خروج دستی',
                            'adjustment' => 'اصلاح صندوق',
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

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('شماره مرجع')
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('ثبت‌کننده')
                    ->placeholder('سیستم')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_id')
                    ->label('پرداخت')
                    ->formatStateUsing(
                        fn ($state) => $state ? '#' . $state : '-'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('expense_id')
                    ->label('هزینه')
                    ->formatStateUsing(
                        fn ($state) => $state ? '#' . $state : '-'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('accounting_transaction_id')
                    ->label('تراکنش حسابداری')
                    ->formatStateUsing(
                        fn ($state) => $state ? '#' . $state : '-'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('description')
                    ->label('توضیحات')
                    ->limit(40)
                    ->placeholder('-')
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
                        'income' => 'ورودی',
                        'expense' => 'هزینه',
                        'refund' => 'برگشت وجه',
                        'cash_in' => 'ورود دستی',
                        'cash_out' => 'خروج دستی',
                        'adjustment' => 'اصلاح صندوق',
                    ]),

                Tables\Filters\SelectFilter::make('cash_register_id')
                    ->label('صندوق')
                    ->relationship(
                        'cashRegister',
                        'id'
                    )
                    ->searchable()
                    ->preload(),

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
            'index' => Pages\ListCashRegisterTransactions::route('/'),
            'view' => Pages\ViewCashRegisterTransaction::route('/{record}'),
        ];
    }
}
