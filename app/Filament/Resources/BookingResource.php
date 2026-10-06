<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Helpers\JalaliHelper;
use App\Models\Booking;
use App\Models\Client;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDatePicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'رزروها';

    protected static ?string $modelLabel = 'رزرو';

    protected static ?string $pluralModelLabel = 'رزروها';

    protected static ?string $navigationGroup = 'NIL';

    public static function form(Form $form): Form
    {
        return $form->schema([

            /*
            |--------------------------------------------------------------------------
            | اطلاعات رزرو
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('اطلاعات رزرو')
                ->schema([

                    Forms\Components\Select::make('client_id')
                        ->label('مشتری')
                        ->options(
                            Client::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(function ($client) {
                                    return [
                                        $client->id => ($client->name ?: 'بدون نام')
                                            . ' - '
                                            . $client->phone,
                                    ];
                                })
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    /*
                    |--------------------------------------------------------------------------
                    | تاریخ رزرو - شمسی
                    |--------------------------------------------------------------------------
                    */

                    JalaliDatePicker::make('booking_date')
                        ->label('تاریخ رزرو')
                        ->displayFormat('Y/m/d')
                        ->native(false)
                        ->required(),

                    Forms\Components\TimePicker::make('start_time')
                        ->label('زمان شروع')
                        ->seconds(false)
                        ->required(),

                    Forms\Components\TimePicker::make('end_time')
                        ->label('زمان پایان')
                        ->seconds(false)
                        ->required(),

                ])
                ->columns(2),

            /*
            |--------------------------------------------------------------------------
            | مالی
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('مالی')
                ->schema([

                    Forms\Components\TextInput::make('subtotal')
                        ->label('مبلغ کل')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\TextInput::make('deposit_amount')
                        ->label('مبلغ بیعانه')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\TextInput::make('paid_amount')
                        ->label('مبلغ پرداخت‌شده')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\Select::make('payment_status')
                        ->label('وضعیت پرداخت')
                        ->options([
                            'pending' => 'در انتظار',
                            'paid' => 'پرداخت‌شده',
                            'failed' => 'ناموفق',
                            'refunded' => 'مستردشده',
                        ])
                        ->required()
                        ->default('pending'),

                ])
                ->columns(2),

            /*
            |--------------------------------------------------------------------------
            | وضعیت
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('وضعیت')
                ->schema([

                    Forms\Components\Select::make('status')
                        ->label('وضعیت رزرو')
                        ->options([
                            'pending' => 'در انتظار',
                            'awaiting_payment' => 'در انتظار پرداخت',
                            'confirmed' => 'تأییدشده',
                            'completed' => 'تکمیل‌شده',
                            'cancelled' => 'لغوشده',
                            'rejected' => 'ردشده',
                            'no_show' => 'عدم حضور',
                        ])
                        ->required()
                        ->default('pending'),

                    /*
                    |--------------------------------------------------------------------------
                    | فعلاً DateTimePicker اصلی
                    |--------------------------------------------------------------------------
                    |
                    | تاریخ + ساعت لغو را در مرحله بعد به نسخه شمسی DateTime
                    | تبدیل می‌کنیم تا ساعت و ذخیره Gregorian دچار مشکل نشود.
                    |
                    */

                    JalaliDateTimePicker::make('cancelled_at')
                        ->label('زمان لغو')
                        ->displayFormat('Y/m/d H:i')
                        ->seconds(false)
                        ->native(false),

                    Forms\Components\Textarea::make('cancellation_reason')
                        ->label('دلیل لغو')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label('یادداشت‌ها')
                        ->rows(4)
                        ->columnSpanFull(),

                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('مشتری')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.phone')
                    ->label('شماره تلفن')
                    ->searchable(),

                /*
                |--------------------------------------------------------------------------
                | تاریخ رزرو - نمایش شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('booking_date')
                    ->label('تاریخ')
                    ->formatStateUsing(
                        fn ($state) => JalaliHelper::date(
                            $state,
                            'Y/m/d'
                        )
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('شروع'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('پایان'),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('مبلغ کل')
                    ->money('IRR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('deposit_amount')
                    ->label('بیعانه')
                    ->money('IRR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('پرداخت‌شده')
                    ->money('IRR')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('وضعیت')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'awaiting_payment',
                        'primary' => 'confirmed',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                        'gray' => 'rejected',
                    ]),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('پرداخت')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),

                /*
                |--------------------------------------------------------------------------
                | تاریخ ایجاد - نمایش شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('created_at')
                    ->label('ایجادشده')
                    ->formatStateUsing(
                        fn ($state) => JalaliHelper::dateTime(
                            $state,
                            'Y/m/d H:i'
                        )
                    )
                    ->sortable(),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'awaiting_payment' => 'در انتظار پرداخت',
                        'confirmed' => 'تأییدشده',
                        'completed' => 'تکمیل‌شده',
                        'cancelled' => 'لغوشده',
                        'rejected' => 'ردشده',
                        'no_show' => 'عدم حضور',
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('وضعیت پرداخت')
                    ->options([
                        'pending' => 'در انتظار',
                        'paid' => 'پرداخت‌شده',
                        'failed' => 'ناموفق',
                        'refunded' => 'مستردشده',
                    ]),

                /*
                |--------------------------------------------------------------------------
                | فیلتر تاریخ رزرو - شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Filters\Filter::make('booking_date')
                    ->label('تاریخ رزرو')
                    ->form([

                        JalaliDatePicker::make('from')
                            ->label('از تاریخ')
                            ->displayFormat('Y/m/d')
                            ->native(false),

                        JalaliDatePicker::make('until')
                            ->label('تا تاریخ')
                            ->displayFormat('Y/m/d')
                            ->native(false),

                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn ($query, $date) =>
                                $query->whereDate(
                                    'booking_date',
                                    '>=',
                                    $date
                                )
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn ($query, $date) =>
                                $query->whereDate(
                                    'booking_date',
                                    '<=',
                                    $date
                                )
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BookingServicesRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
