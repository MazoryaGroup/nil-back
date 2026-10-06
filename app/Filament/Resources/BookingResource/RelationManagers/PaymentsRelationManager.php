<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Helpers\JalaliHelper;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'پرداخت‌ها';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Select::make('client_id')
                    ->label('مشتری')
                    ->options(
                        Client::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(function (Client $client) {
                                return [
                                    $client->id => ($client->name ?: 'بدون نام')
                                        . ' - '
                                        . $client->phone,
                                ];
                            })
                            ->toArray()
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->label('مبلغ')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                Forms\Components\Select::make('type')
                    ->label('نوع پرداخت')
                    ->options([
                        'deposit' => 'بیعانه',
                        'remaining' => 'تسویه',
                        'refund' => 'بازپرداخت',
                    ])
                    ->required()
                    ->default('deposit'),

                Forms\Components\Select::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'paid' => 'پرداخت‌شده',
                        'failed' => 'ناموفق',
                        'refunded' => 'بازپرداخت‌شده',
                    ])
                    ->required()
                    ->default('pending'),

                Forms\Components\TextInput::make('gateway')
                    ->label('درگاه')
                    ->maxLength(100),

                Forms\Components\TextInput::make('transaction_id')
                    ->label('شناسه تراکنش')
                    ->maxLength(255),

                /*
                |--------------------------------------------------------------------------
                | زمان پرداخت
                |--------------------------------------------------------------------------
                |
                | فعلاً DateTimePicker استاندارد باقی می‌ماند.
                | نمایش آن در جدول شمسی شده است.
                |
                */

                JalaliDateTimePicker::make('paid_at')
                    ->label('زمان پرداخت')
                    ->displayFormat('Y/m/d H:i')
                    ->seconds(false)
                    ->native(false),

            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('مشتری')
                    ->formatStateUsing(
                        fn ($state, $record) =>
                        $state ?: 'بدون نام'
                    )
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ')
                    ->money('IRR')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('نوع')
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'deposit' => 'بیعانه',
                            'remaining' => 'تسویه',
                            'refund' => 'بازپرداخت',
                            default => $state ?? '-',
                        }
                    )
                    ->colors([
                        'primary' => 'deposit',
                        'success' => 'remaining',
                        'danger' => 'refund',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('وضعیت')
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'pending' => 'در انتظار',
                            'paid' => 'پرداخت‌شده',
                            'failed' => 'ناموفق',
                            'refunded' => 'بازپرداخت‌شده',
                            default => $state ?? '-',
                        }
                    )
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),

                Tables\Columns\TextColumn::make('gateway')
                    ->label('درگاه')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('شناسه تراکنش')
                    ->limit(25)
                    ->placeholder('-'),

                /*
                |--------------------------------------------------------------------------
                | زمان پرداخت - شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('زمان پرداخت')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime(
                                $state,
                                'Y/m/d H:i'
                            )
                            : '-'
                    )
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | زمان ایجاد - شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('created_at')
                    ->label('ایجادشده')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime(
                                $state,
                                'Y/m/d H:i'
                            )
                            : '-'
                    )
                    ->sortable(),

            ])
            ->headerActions([

                Tables\Actions\CreateAction::make()
                    ->label('افزودن پرداخت'),

            ])
            ->actions([

                Tables\Actions\EditAction::make()
                    ->label('ویرایش'),

                Tables\Actions\DeleteAction::make()
                    ->label('حذف'),

            ])
            ->bulkActions([

                Tables\Actions\BulkActionGroup::make([

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('حذف انتخاب‌شده‌ها'),

                ]),

            ]);
    }
}
