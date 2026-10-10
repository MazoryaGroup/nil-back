<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Helpers\JalaliHelper;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'هزینه‌ها';

    protected static ?string $modelLabel = 'هزینه';

    protected static ?string $pluralModelLabel = 'هزینه‌ها';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Select::make('expense_category_id')
                    ->label('دسته‌بندی هزینه')
                    ->relationship(
                        name: 'category',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) =>
                        $query->where('is_active', true)
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->label('مبلغ')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('تومان')
                    ->required(),

                Forms\Components\Select::make('payment_method')
                    ->label('روش پرداخت')
                    ->options([
                        'cash' => 'نقدی',
                        'pos' => 'کارتخوان',
                        'bank_transfer' => 'انتقال بانکی',
                        'other' => 'سایر',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('reference_number')
                    ->label('شماره مرجع')
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expense_date')
                    ->label('تاریخ')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime($state, 'Y/m/d H:i')
                            : '-'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('دسته‌بندی')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ')
                    ->formatStateUsing(
                        fn ($state) => number_format((float) $state) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('روش پرداخت')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'نقدی',
                        'pos' => 'کارتخوان',
                        'bank_transfer' => 'انتقال بانکی',
                        'other' => 'سایر',
                        default => $state ?? '-',
                    }),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('شماره مرجع')
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('ثبت‌کننده')
                    ->placeholder('سیستم')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('accounting_transaction_id')
                    ->label('تراکنش حسابداری')
                    ->formatStateUsing(
                        fn ($state) => $state ? '#' . $state : '-'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('description')
                    ->label('توضیحات')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->label('دسته‌بندی')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('روش پرداخت')
                    ->options([
                        'cash' => 'نقدی',
                        'pos' => 'کارتخوان',
                        'bank_transfer' => 'انتقال بانکی',
                        'other' => 'سایر',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('مشاهده'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
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
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
        ];
    }
}
