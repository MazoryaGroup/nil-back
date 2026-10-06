<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountCodeResource\Pages;
use App\Helpers\JalaliHelper;
use App\Models\DiscountCode;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'کدهای تخفیف';

    protected static ?string $modelLabel = 'کد تخفیف';

    protected static ?string $pluralModelLabel = 'کدهای تخفیف';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('code')
                    ->label('کد تخفیف')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100)
                    ->helperText('مثلاً NIL10'),

                Forms\Components\Select::make('type')
                    ->label('نوع تخفیف')
                    ->options([
                        'percentage' => 'درصدی',
                        'fixed' => 'مبلغ ثابت',
                    ])
                    ->required()
                    ->default('percentage')
                    ->live(),

                Forms\Components\TextInput::make('value')
                    ->label('مقدار تخفیف')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->helperText('درصد یا مبلغ ثابت'),

                Forms\Components\TextInput::make('min_order_amount')
                    ->label('حداقل مبلغ رزرو')
                    ->numeric()
                    ->minValue(0)
                    ->nullable(),

                Forms\Components\TextInput::make('max_discount_amount')
                    ->label('سقف تخفیف')
                    ->numeric()
                    ->minValue(0)
                    ->nullable(),

                Forms\Components\TextInput::make('usage_limit')
                    ->label('حداکثر تعداد استفاده')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->nullable(),

                Forms\Components\TextInput::make('usage_limit_per_client')
                    ->label('حداکثر استفاده هر مشتری')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->nullable(),

                /*
                |--------------------------------------------------------------------------
                | شروع اعتبار - شمسی
                |--------------------------------------------------------------------------
                */

                JalaliDateTimePicker::make('starts_at')
                    ->label('شروع اعتبار')
                    ->displayFormat('Y/m/d H:i')
                    ->seconds(false)
                    ->native(false)
                    ->nullable(),

                /*
                |--------------------------------------------------------------------------
                | پایان اعتبار - شمسی
                |--------------------------------------------------------------------------
                */

                JalaliDateTimePicker::make('expires_at')
                    ->label('پایان اعتبار')
                    ->displayFormat('Y/m/d H:i')
                    ->seconds(false)
                    ->native(false)
                    ->nullable(),

                Forms\Components\Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('code')
                    ->label('کد')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->formatStateUsing(
                        fn (string $state) =>
                        $state === 'percentage'
                            ? 'درصدی'
                            : 'مبلغ ثابت'
                    ),

                Tables\Columns\TextColumn::make('value')
                    ->label('مقدار')
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('تعداد استفاده')
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | تاریخ انقضا - نمایش شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('انقضا')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime(
                                $state,
                                'Y/m/d H:i'
                            )
                            : 'بدون انقضا'
                    )
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean(),

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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscountCodes::route('/'),
            'create' => Pages\CreateDiscountCode::route('/create'),
            'edit' => Pages\EditDiscountCode::route('/{record}/edit'),
        ];
    }
}
