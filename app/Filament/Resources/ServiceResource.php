<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Helpers\JalaliHelper;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $navigationLabel = 'خدمات';

    protected static ?string $modelLabel = 'خدمت';

    protected static ?string $pluralModelLabel = 'خدمات';

    protected static ?string $navigationGroup = 'NIL';

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\TextInput::make('name')
                ->label('نام خدمت')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->label('توضیحات')
                ->rows(4)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('duration')
                ->label('مدت زمان (دقیقه)')
                ->numeric()
                ->minValue(1)
                ->required()
                ->default(60),

            Forms\Components\TextInput::make('price')
                ->label('قیمت')
                ->numeric()
                ->minValue(0)
                ->required()
                ->default(0),

            Forms\Components\TextInput::make('deposit_amount')
                ->label('مبلغ بیعانه')
                ->numeric()
                ->minValue(0)
                ->required()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->label('فعال')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('خدمت')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('مدت زمان')
                    ->suffix(' دقیقه')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('قیمت')
                    ->money('IRR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('deposit_amount')
                    ->label('بیعانه')
                    ->money('IRR')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime($state, 'Y/m/d H:i')
                            : '-'
                    )
                    ->sortable(),
            ])
            ->filters([

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('فعال'),

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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}

