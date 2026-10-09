<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Helpers\JalaliHelper;
use App\Models\Booking;
use App\Models\Client;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDatePicker;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'رزروها';

    protected static ?string $modelLabel = 'رزرو';

    protected static ?string $pluralModelLabel = 'رزروها';

    protected static ?string $navigationGroup = 'NIL';

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public static function form(Form $form): Form
    {
        return $form->schema([

            /*
            |--------------------------------------------------------------------------
            | Booking Information
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
                                        $client->id =>
                                            ($client->name ?: 'بدون نام')
                                            . ' - '
                                            . $client->phone,
                                    ];
                                })
                                ->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

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
            | Financial Information
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('اطلاعات مالی')
                ->description('تمام مبالغ به تومان نمایش داده می‌شوند.')
                ->schema([

                    Forms\Components\TextInput::make('subtotal')
                        ->label('جمع قیمت اولیه خدمات')
                        ->numeric()
                        ->suffix('تومان')
                        ->minValue(0)
                        ->default(0)
                        ->readOnly()
                        ->required(),

                    Forms\Components\TextInput::make('discount_amount')
                        ->label('مبلغ تخفیف')
                        ->numeric()
                        ->suffix('تومان')
                        ->minValue(0)
                        ->default(0)
                        ->readOnly(),

                    Forms\Components\TextInput::make('total_amount')
                        ->label('مبلغ نهایی رزرو')
                        ->numeric()
                        ->suffix('تومان')
                        ->minValue(0)
                        ->default(0)
                        ->readOnly(),

                    Forms\Components\TextInput::make('deposit_amount')
                        ->label('مبلغ بیعانه')
                        ->numeric()
                        ->suffix('تومان')
                        ->minValue(0)
                        ->default(0)
                        ->readOnly()
                        ->required(),

                    Forms\Components\TextInput::make('paid_amount')
                        ->label('مبلغ پرداخت‌شده')
                        ->numeric()
                        ->suffix('تومان')
                        ->minValue(0)
                        ->default(0)
                        ->readOnly()
                        ->required(),

                    Forms\Components\Placeholder::make('remaining_amount')
                        ->label('مانده قابل پرداخت')
                        ->content(function (?Booking $record): string {
                            if (!$record) {
                                return 'پس از ثبت رزرو محاسبه می‌شود.';
                            }

                            $remaining = max(
                                0,
                                (float) $record->total_amount
                                - (float) $record->paid_amount
                            );

                            return number_format($remaining) . ' تومان';
                        }),

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
            | Booking Status
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('وضعیت رزرو')
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

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

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
                | Jalali Date
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('booking_date')
                    ->label('تاریخ رزرو')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::date($state, 'Y/m/d')
                            : '-'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('شروع'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('پایان'),

                /*
                |--------------------------------------------------------------------------
                | Financial Columns - Toman
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('جمع قیمت خدمات')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('تخفیف')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('مبلغ نهایی')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('deposit_amount')
                    ->label('بیعانه')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('پرداخت‌شده')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | Remaining Amount
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('مانده پرداخت')
                    ->state(
                        fn (Booking $record): float => max(
                            0,
                            round(
                                (float) $record->total_amount
                                - (float) $record->paid_amount,
                                2
                            )
                        )
                    )
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->color(
                        fn ($state) =>
                        (float) $state > 0 ? 'danger' : 'success'
                    ),

                /*
                |--------------------------------------------------------------------------
                | Overpaid Amount
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('overpaid_amount')
                    ->label('اضافه‌پرداخت')
                    ->state(
                        fn (Booking $record): float => max(
                            0,
                            round(
                                (float) $record->paid_amount
                                - (float) $record->total_amount,
                                2
                            )
                        )
                    )
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->color(
                        fn ($state) =>
                        (float) $state > 0 ? 'warning' : 'gray'
                    )
                    ->weight(
                        fn ($state) =>
                        (float) $state > 0 ? 'bold' : 'normal'
                    ),

                /*
                |--------------------------------------------------------------------------
                | Refund Review Status
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('refund_review_status')
                    ->label('بررسی اضافه‌پرداخت')
                    ->state(
                        function (Booking $record): string {

                            $overpaid = round(
                                (float) $record->paid_amount
                                - (float) $record->total_amount,
                                2
                            );

                            return $overpaid > 0
                                ? 'نیازمند بررسی'
                                : 'بدون اضافه‌پرداخت';
                        }
                    )
                    ->badge()
                    ->color(
                        fn (string $state) =>
                        $state === 'نیازمند بررسی'
                            ? 'warning'
                            : 'success'
                    ),

                /*
                |--------------------------------------------------------------------------
                | Booking Status
                |--------------------------------------------------------------------------
                */

                Tables\Columns\BadgeColumn::make('status')
                    ->label('وضعیت رزرو')
                    ->formatStateUsing(
                        fn ($state) => match ($state) {
                            'pending' => 'در انتظار',
                            'awaiting_payment' => 'در انتظار پرداخت',
                            'confirmed' => 'تأییدشده',
                            'completed' => 'تکمیل‌شده',
                            'cancelled' => 'لغوشده',
                            'rejected' => 'ردشده',
                            'no_show' => 'عدم حضور',
                            default => $state,
                        }
                    )
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'awaiting_payment',
                        'primary' => 'confirmed',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                        'gray' => 'rejected',
                    ]),

                /*
                |--------------------------------------------------------------------------
                | Payment Status
                |--------------------------------------------------------------------------
                */

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('وضعیت پرداخت')
                    ->formatStateUsing(
                        fn ($state) => match ($state) {
                            'pending' => 'در انتظار',
                            'paid' => 'پرداخت‌شده',
                            'failed' => 'ناموفق',
                            'refunded' => 'مستردشده',
                            default => $state,
                        }
                    )
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),

                /*
                |--------------------------------------------------------------------------
                | Created At
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
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

            /*
            |--------------------------------------------------------------------------
            | Filters
            |--------------------------------------------------------------------------
            */

            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت رزرو')
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

            /*
            |--------------------------------------------------------------------------
            | Actions
            |--------------------------------------------------------------------------
            */

            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('ویرایش'),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relation Managers
    |--------------------------------------------------------------------------
    */

    public static function getRelations(): array
    {
        return [
            RelationManagers\BookingServicesRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
