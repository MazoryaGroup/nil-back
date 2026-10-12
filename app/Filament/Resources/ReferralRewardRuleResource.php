<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferralRewardRuleResource\Pages;
use App\Models\ReferralRewardRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReferralRewardRuleResource extends Resource
{
    protected static ?string $model = ReferralRewardRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'پاداش معرفی';

    protected static ?string $modelLabel = 'قانون پاداش';

    protected static ?string $pluralModelLabel = 'قوانین پاداش معرفی';

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\TextInput::make('referral_count')
                ->label('تعداد معرفی موفق')
                ->integer()
                ->required()
                ->minValue(1)
                ->unique(ignoreRecord: true)
                ->helperText('برای هر تعداد معرفی فقط یک قانون ثبت می‌شود.'),

            Forms\Components\Select::make('type')
                ->label('نوع پاداش')
                ->options([
                    'percentage' => 'درصدی',
                    'fixed' => 'مبلغ ثابت',
                ])
                ->required()
                ->default('percentage')
                ->live(),

            Forms\Components\TextInput::make('value')
                ->label('مقدار پاداش')
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
                        ? 'درصد باید بیشتر از صفر و حداکثر ۱۰۰ باشد.'
                        : 'مبلغ پاداش باید بیشتر از صفر باشد.'
                ),

            Forms\Components\TextInput::make('max_discount_amount')
                ->label('سقف تخفیف')
                ->numeric()
                ->nullable()
                ->rules(['gt:0'])
                ->helperText('خالی بماند یعنی بدون سقف جداگانه.'),

            Forms\Components\Toggle::make('is_active')
                ->label('فعال')
                ->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('referral_count')
                    ->label('تعداد معرفی')
                    ->sortable(),

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

                Tables\Columns\TextColumn::make('max_discount_amount')
                    ->label('سقف تخفیف')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('بدون سقف'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean(),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('referral_count', 'asc');
    }

    // جلوگیری از حذف قوانین و آسیب به سوابق پاداش
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
            'index' => Pages\ListReferralRewardRules::route('/'),
            'create' => Pages\CreateReferralRewardRule::route('/create'),
            'edit' => Pages\EditReferralRewardRule::route('/{record}/edit'),
        ];
    }
}

