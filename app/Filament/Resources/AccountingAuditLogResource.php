<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingAuditLogResource\Pages;
use App\Models\AccountingAuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Helpers\JalaliHelper;

class AccountingAuditLogResource extends Resource
{
    protected static ?string $model = AccountingAuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'لاگ حسابداری';

    protected static ?string $modelLabel = 'لاگ حسابداری';

    protected static ?string $pluralModelLabel = 'لاگ‌های حسابداری';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('id')
                    ->label('ID')
                    ->disabled(),

                Forms\Components\TextInput::make('action')
                    ->label('عملیات')
                    ->disabled(),

                Forms\Components\TextInput::make('entity_type')
                    ->label('نوع رکورد')
                    ->disabled(),

                Forms\Components\TextInput::make('entity_id')
                    ->label('شناسه رکورد')
                    ->disabled(),

                Forms\Components\TextInput::make('user.name')
                    ->label('کاربر انجام‌دهنده')
                    ->disabled(),

                Forms\Components\TextInput::make('booking_id')
                    ->label('Booking ID')
                    ->disabled(),

                Forms\Components\TextInput::make('client_id')
                    ->label('Client ID')
                    ->disabled(),

                Forms\Components\Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(3)
                    ->columnSpanFull()
                    ->disabled(),

                Forms\Components\Textarea::make('old_values')
                    ->label('مقادیر قبلی')
                    ->formatStateUsing(
                        fn ($state) =>
                        $state
                            ? json_encode(
                            $state,
                            JSON_PRETTY_PRINT |
                            JSON_UNESCAPED_UNICODE
                        )
                            : null
                    )
                    ->rows(12)
                    ->columnSpanFull()
                    ->disabled(),

                Forms\Components\Textarea::make('new_values')
                    ->label('مقادیر جدید')
                    ->formatStateUsing(
                        fn ($state) =>
                        $state
                            ? json_encode(
                            $state,
                            JSON_PRETTY_PRINT |
                            JSON_UNESCAPED_UNICODE
                        )
                            : null
                    )
                    ->rows(12)
                    ->columnSpanFull()
                    ->disabled(),

                Forms\Components\TextInput::make('ip_address')
                    ->label('IP Address')
                    ->disabled(),

                Forms\Components\Textarea::make('user_agent')
                    ->label('User Agent')
                    ->rows(3)
                    ->columnSpanFull()
                    ->disabled(),

                Forms\Components\TextInput::make('created_at')
                    ->label('زمان ثبت')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime($state, 'Y/m/d H:i:s')
                            : '-'
                    )
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')

            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('زمان')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime($state, 'Y/m/d H:i:s')
                            : '-'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('عملیات')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('entity_type')
                    ->label('نوع رکورد')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('entity_id')
                    ->label('شناسه')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('کاربر')
                    ->placeholder('سیستم')
                    ->searchable(),

                Tables\Columns\TextColumn::make('booking_id')
                    ->label('Booking')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('client_id')
                    ->label('Client')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('توضیحات')
                    ->limit(50)
                    ->tooltip(
                        fn (Tables\Columns\TextColumn $column) =>
                        $column->getState()
                    ),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([

                Tables\Filters\SelectFilter::make('action')
                    ->label('عملیات')
                    ->options([
                        'payment_paid' => 'ثبت پرداخت',
                        'refund_created' => 'برگشت وجه',
                        'expense_created' => 'ثبت هزینه',
                        'cash_in' => 'ورود وجه نقد',
                        'cash_out' => 'خروج وجه نقد',
                        'cash_register_closed' => 'بستن صندوق',
                    ]),

                Tables\Filters\SelectFilter::make('entity_type')
                    ->label('نوع رکورد')
                    ->options([
                        'payment' => 'پرداخت',
                        'expense' => 'هزینه',
                        'cash_register' => 'صندوق',
                        'cash_register_transaction' => 'گردش صندوق',
                    ]),
            ])

            ->actions([

                Tables\Actions\ViewAction::make()
                    ->label('مشاهده'),
            ])

            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingAuditLogs::route('/'),
            'view' => Pages\ViewAccountingAuditLog::route('/{record}'),
        ];
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
}
