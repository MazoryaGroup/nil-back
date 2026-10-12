<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountCodeResource\Pages;
use App\Helpers\JalaliHelper;
use App\Models\DiscountCode;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDateTimePicker;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'کدهای تخفیف';

    protected static ?string $modelLabel = 'کد تخفیف';

    protected static ?string $pluralModelLabel = 'کدهای تخفیف';

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\TextInput::make('code')
                ->label('کد تخفیف')
                ->required()
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(
                    fn ($state, Set $set) =>
                    $set('code', strtoupper(trim((string) $state)))
                )
                ->dehydrateStateUsing(
                    fn ($state) => strtoupper(trim((string) $state))
                )
                ->rule(
                    fn (?DiscountCode $record) =>
                    function (
                        string $attribute,
                               $value,
                        Closure $fail
                    ) use ($record): void {

                        $code = strtoupper(trim((string) $value));

                        if ($code === '') {
                            $fail('کد تخفیف نمی‌تواند خالی باشد.');
                            return;
                        }

                        $query = DiscountCode::query()
                            ->whereRaw(
                                'UPPER(TRIM(code)) = ?',
                                [$code]
                            );

                        if ($record) {
                            $query->where(
                                'id',
                                '!=',
                                $record->getKey()
                            );
                        }

                        if ($query->exists()) {
                            $fail('این کد تخفیف قبلاً ثبت شده است.');
                        }
                    }
                )
                ->helperText('مثلاً NIL10 - کد به حروف بزرگ ذخیره می‌شود.'),

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
                ->rules(
                    fn (Get $get): array =>
                    $get('type') === 'percentage'
                        ? ['gt:0', 'lte:100']
                        : ['gt:0']
                )
                ->helperText(
                    fn (Get $get) =>
                    $get('type') === 'percentage'
                        ? 'درصد تخفیف باید بیشتر از صفر و حداکثر ۱۰۰ باشد.'
                        : 'مبلغ ثابت تخفیف باید بیشتر از صفر باشد.'
                ),

            Forms\Components\TextInput::make('min_order_amount')
                ->label('حداقل مبلغ رزرو')
                ->numeric()
                ->minValue(0)
                ->nullable(),

            Forms\Components\TextInput::make('max_discount_amount')
                ->label('سقف تخفیف')
                ->numeric()
                ->nullable()
                ->rules(['gt:0'])
                ->helperText('خالی بماند یعنی بدون سقف جداگانه.'),

            Forms\Components\TextInput::make('usage_limit')
                ->label('حداکثر تعداد استفاده')
                ->integer()
                ->minValue(1)
                ->nullable()
                ->helperText('خالی بماند یعنی نامحدود.'),

            Forms\Components\TextInput::make('usage_limit_per_client')
                ->label('حداکثر استفاده هر مشتری')
                ->integer()
                ->minValue(1)
                ->nullable()
                ->helperText('خالی بماند یعنی بدون محدودیت برای هر مشتری.'),

            JalaliDateTimePicker::make('starts_at')
                ->label('شروع اعتبار')
                ->displayFormat('Y/m/d H:i')
                ->seconds(false)
                ->native(false)
                ->nullable(),

            JalaliDateTimePicker::make('expires_at')
                ->label('پایان اعتبار')
                ->displayFormat('Y/m/d H:i')
                ->seconds(false)
                ->native(false)
                ->nullable()
                ->rules(
                    fn (Get $get): array =>
                    filled($get('starts_at'))
                        ? ['after:starts_at']
                        : []
                )
                ->validationMessages([
                    'after' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.',
                ]),

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
                    ->label('کد تخفیف')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->formatStateUsing(
                        fn ($state) =>
                        $state === 'percentage'
                            ? 'درصدی'
                            : 'مبلغ ثابت'
                    ),

                Tables\Columns\TextColumn::make('value')
                    ->label('مقدار')
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('تعداد استفاده')
                    ->sortable()
                    ->default(0),

                Tables\Columns\TextColumn::make('usage_limit')
                    ->label('محدودیت کل')
                    ->placeholder('نامحدود'),

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
            ])
            ->defaultSort('id', 'desc');
    }

    // حفظ سوابق تخفیف: به جای حذف، غیرفعال شود.
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
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
