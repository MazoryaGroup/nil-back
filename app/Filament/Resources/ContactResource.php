<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Helpers\JalaliHelper;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'تماس با ما';

    protected static ?string $modelLabel = 'تماس';

    protected static ?string $pluralModelLabel = 'تماس‌ها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->label('نام')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('last_name')
                    ->label('نام خانوادگی')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('ایمیل')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('phone')
                    ->label('تلفن')
                    ->tel()
                    ->maxLength(15),

                Forms\Components\Textarea::make('message')
                    ->label('پیام')
                    ->required()
                    ->maxLength(5000)
                    ->rows(6)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('شناسه')
                    ->sortable(),

                Tables\Columns\TextColumn::make('first_name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_name')
                    ->label('نام خانوادگی')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('تلفن')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('ایمیل')
                    ->searchable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('پیام')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime($state, 'Y/m/d H:i')
                            : '-'
                    )
                    ->sortable(),
            ])

            ->filters([
                //
            ])

            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\DeleteAction::make(),
            ])

            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContact::route('/'),
        ];
    }
}
