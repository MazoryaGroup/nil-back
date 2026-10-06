<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferralRewardRuleResource\Pages;
use App\Models\ReferralRewardRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReferralRewardRuleResource extends Resource
{
    protected static ?string $model = ReferralRewardRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'پاداش معرفی';

    protected static ?string $modelLabel = 'قانون پاداش';

    protected static ?string $pluralModelLabel = 'قوانین پاداش معرفی';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('referral_count')
                    ->label('تعداد معرفی موفق')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(1),

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
                    ->minValue(0)
                    ->helperText('درصد یا مبلغ ثابت'),

                Forms\Components\TextInput::make('max_discount_amount')
                    ->label('سقف تخفیف')
                    ->numeric()
                    ->minValue(0)
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
                Tables\Columns\TextColumn::make('referral_count')
                    ->label('تعداد معرفی')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->formatStateUsing(fn (string $state) =>
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
            'index' => Pages\ListReferralRewardRules::route('/'),
            'create' => Pages\CreateReferralRewardRule::route('/create'),
            'edit' => Pages\EditReferralRewardRule::route('/{record}/edit'),
        ];
    }
}
