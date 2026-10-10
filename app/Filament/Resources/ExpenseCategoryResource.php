<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'دسته‌بندی هزینه‌ها';

    protected static ?string $modelLabel = 'دسته‌بندی هزینه';

    protected static ?string $pluralModelLabel = 'دسته‌بندی هزینه‌ها';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\TextInput::make('name')
                ->label('نام دسته‌بندی')
                ->required()
                ->maxLength(255),

            Forms\Components\Toggle::make('is_active')
                ->label('فعال')
                ->default(true),

            Forms\Components\Textarea::make('description')
                ->label('توضیحات')
                ->rows(3)
                ->columnSpanFull(),

        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('نام دسته‌بندی')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expenses_count')
                    ->label('تعداد هزینه‌ها')
                    ->counts('expenses')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean(),

                Tables\Columns\TextColumn::make('description')
                    ->label('توضیحات')
                    ->limit(50)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

            ])
            ->filters([

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('وضعیت فعال'),

            ])
            ->actions([

                Tables\Actions\EditAction::make()
                    ->label('ویرایش'),

                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->visible(
                        fn (ExpenseCategory $record): bool =>
                        !$record->expenses()->exists()
                    ),

            ])
            ->bulkActions([]);
    }

    public static function canDelete(Model $record): bool
    {
        return !$record->expenses()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'edit' => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
