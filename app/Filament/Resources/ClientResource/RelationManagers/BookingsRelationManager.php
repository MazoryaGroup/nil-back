<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\Booking;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'تاریخچه رزروها و خدمات';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query->with([
                    'bookingServices.service',
                    'bookingServices.staff',
                ])
            )
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('شماره رزرو')
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking_date')
                    ->label('تاریخ رزرو')
                    ->date('Y/m/d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('شروع'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('پایان'),

                Tables\Columns\TextColumn::make('services_list')
                    ->label('خدمات')
                    ->state(function (Booking $record): string {
                        return $record->bookingServices
                            ->map(
                                fn ($item) =>
                                    $item->service?->name ?? '-'
                            )
                            ->implode('، ') ?: '-';
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('staff_list')
                    ->label('پرسنل')
                    ->state(function (Booking $record): string {
                        return $record->bookingServices
                            ->map(
                                fn ($item) =>
                                    $item->staff?->name ?? 'تخصیص نیافته'
                            )
                            ->unique()
                            ->implode('، ') ?: '-';
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت رزرو')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'pending' => 'در انتظار',
                            'awaiting_payment' => 'در انتظار پرداخت',
                            'confirmed' => 'تأییدشده',
                            'completed' => 'تکمیل‌شده',
                            'cancelled' => 'لغوشده',
                            'rejected' => 'ردشده',
                            'no_show' => 'عدم حضور',
                            default => $state ?? '-',
                        }
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'confirmed' => 'success',
                            'completed' => 'success',
                            'cancelled', 'rejected' => 'danger',
                            'pending', 'awaiting_payment' => 'warning',
                            default => 'gray',
                        }
                    ),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('مبلغ نهایی')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    ),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('مبلغ پرداخت‌شده')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    ),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('مانده')
                    ->state(
                        fn (Booking $record) =>
                        $record->remaining_amount
                    )
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    ),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('وضعیت پرداخت')
                    ->badge(),

            ])
            ->defaultSort('booking_date', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
