<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashRegisterResource\Pages;
use App\Helpers\JalaliHelper;
use App\Models\CashRegister;
use App\Services\CashRegisterService;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDatePicker;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class CashRegisterResource extends Resource
{
    protected static ?string $model = CashRegister::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'صندوق';

    protected static ?string $modelLabel = 'صندوق';

    protected static ?string $pluralModelLabel = 'صندوق‌ها';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                /*
                |--------------------------------------------------------------------------
                | تاریخ صندوق - شمسی
                |--------------------------------------------------------------------------
                */

                JalaliDatePicker::make('register_date')
                    ->label('تاریخ صندوق')
                    ->displayFormat('Y/m/d')
                    ->native(false)
                    ->disabled(),

                Forms\Components\Select::make('status')
                    ->label('وضعیت')
                    ->options([
                        'open' => 'باز',
                        'closed' => 'بسته',
                    ])
                    ->disabled(),

                Forms\Components\TextInput::make('opening_balance')
                    ->label('موجودی اولیه')
                    ->suffix('تومان')
                    ->disabled(),

                Forms\Components\TextInput::make('total_income')
                    ->label('کل ورودی نقدی')
                    ->suffix('تومان')
                    ->disabled(),

                Forms\Components\TextInput::make('total_expense')
                    ->label('کل خروجی / هزینه نقدی')
                    ->suffix('تومان')
                    ->disabled(),

                Forms\Components\TextInput::make('total_refund')
                    ->label('کل برگشت وجه نقدی')
                    ->suffix('تومان')
                    ->disabled(),

                Forms\Components\TextInput::make('closing_balance')
                    ->label('موجودی سیستمی')
                    ->suffix('تومان')
                    ->disabled(),

                Forms\Components\TextInput::make('actual_closing_balance')
                    ->label('موجودی واقعی هنگام بستن')
                    ->suffix('تومان')
                    ->disabled(),

                Forms\Components\TextInput::make('cash_difference')
                    ->label('اختلاف صندوق')
                    ->suffix('تومان')
                    ->disabled(),

                Forms\Components\TextInput::make('openedBy.name')
                    ->label('باز شده توسط')
                    ->disabled(),

                Forms\Components\TextInput::make('closedBy.name')
                    ->label('بسته شده توسط')
                    ->disabled(),

                /*
                |--------------------------------------------------------------------------
                | زمان باز شدن - شمسی
                |--------------------------------------------------------------------------
                */

                JalaliDateTimePicker::make('opened_at')
                    ->label('زمان باز شدن')
                    ->displayFormat('Y/m/d H:i')
                    ->seconds(false)
                    ->native(false)
                    ->disabled(),

                /*
                |--------------------------------------------------------------------------
                | زمان بسته شدن - شمسی
                |--------------------------------------------------------------------------
                */

                JalaliDateTimePicker::make('closed_at')
                    ->label('زمان بسته شدن')
                    ->displayFormat('Y/m/d H:i')
                    ->seconds(false)
                    ->native(false)
                    ->disabled(),

                Forms\Components\Textarea::make('notes')
                    ->label('یادداشت')
                    ->rows(3)
                    ->columnSpanFull()
                    ->disabled(),

            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('register_date', 'desc')

            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | تاریخ صندوق - نمایش شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('register_date')
                    ->label('تاریخ')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::date(
                                $state,
                                'Y/m/d'
                            )
                            : '-'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('opening_balance')
                    ->label('موجودی اولیه')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    ),

                Tables\Columns\TextColumn::make('total_income')
                    ->label('ورودی')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_expense')
                    ->label('خروجی')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_refund')
                    ->label('برگشت وجه')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('closing_balance')
                    ->label('موجودی سیستم')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('actual_closing_balance')
                    ->label('موجودی واقعی')
                    ->formatStateUsing(
                        fn ($state) => $state !== null
                            ? number_format((float) $state) . ' تومان'
                            : '-'
                    ),

                Tables\Columns\TextColumn::make('cash_difference')
                    ->label('اختلاف')
                    ->formatStateUsing(
                        fn ($state) => $state !== null
                            ? number_format((float) $state) . ' تومان'
                            : '-'
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'open' => 'باز',
                            'closed' => 'بسته',
                            default => $state ?? '-',
                        }
                    ),

                Tables\Columns\TextColumn::make('openedBy.name')
                    ->label('بازکننده')
                    ->placeholder('سیستم')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('closedBy.name')
                    ->label('بسته‌کننده')
                    ->placeholder('-')
                    ->toggleable(),

            ])

            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت صندوق')
                    ->options([
                        'open' => 'باز',
                        'closed' => 'بسته',
                    ]),

            ])

            ->actions([

                Tables\Actions\ViewAction::make()
                    ->label('مشاهده'),

                Tables\Actions\Action::make('close_register')
                    ->label('بستن صندوق')
                    ->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->modalHeading('بستن صندوق')
                    ->modalDescription(
                        'مبلغ واقعی موجود در صندوق را وارد کنید. سیستم اختلاف موجودی را محاسبه خواهد کرد.'
                    )
                    ->visible(
                        fn (CashRegister $record): bool =>
                            $record->status === 'open'
                    )
                    ->form([

                        Forms\Components\TextInput::make('actual_closing_balance')
                            ->label('موجودی واقعی صندوق')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('تومان')
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('یادداشت')
                            ->rows(3),

                    ])
                    ->action(function (
                        CashRegister $record,
                        array $data
                    ): void {

                        try {

                            app(CashRegisterService::class)
                                ->closeRegister(
                                    register: $record,
                                    actualClosingBalance:
                                    (float) $data['actual_closing_balance'],
                                    closedBy: auth()->id(),
                                    notes: $data['notes'] ?? null,
                                );

                            Notification::make()
                                ->title('صندوق با موفقیت بسته شد')
                                ->success()
                                ->send();

                        } catch (Throwable $e) {

                            Notification::make()
                                ->title('خطا در بستن صندوق')
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
            'index' => Pages\ListCashRegisters::route('/'),
            'view' => Pages\ViewCashRegister::route('/{record}'),
        ];
    }
}
