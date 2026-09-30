<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\BookingResource\RelationManagers;

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

                    Forms\Components\DatePicker::make('booking_date')
                        ->label('تاریخ')
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

                    Forms\Components\DateTimePicker::make('cancelled_at')
                        ->label('زمان لغو')
                        ->seconds(false),

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

                Tables\Columns\TextColumn::make('booking_date')
                    ->label('تاریخ')
                    ->date('Y-m-d')
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('ایجادشده')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('status')
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
                    ->options([
                        'pending' => 'در انتظار',
                        'paid' => 'پرداخت‌شده',
                        'failed' => 'ناموفق',
                        'refunded' => 'مستردشده',
                    ]),

                Tables\Filters\Filter::make('booking_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('از تاریخ'),

                        Forms\Components\DatePicker::make('until')
                            ->label('تا تاریخ'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn ($query, $date) =>
                                $query->whereDate('booking_date', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn ($query, $date) =>
                                $query->whereDate('booking_date', '<=', $date)
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

