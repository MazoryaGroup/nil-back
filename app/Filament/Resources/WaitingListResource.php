<?php

namespace App\Filament\Resources;

use App\Exports\UsersExport;
use App\Filament\Resources\WaitingListResource\Pages;
use App\Models\WaitingList;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class WaitingListResource extends Resource
{
    protected static ?string $model = WaitingList::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'لیست انتظار';
    protected static ?string $modelLabel = 'لیست انتظار';
    protected static ?string $pluralLabel = 'لیست انتظار';

//    public static function form(Form $form): Form
//    {
//        return $form->schema([
//            Forms\Components\TextInput::make('full_name')
//                ->required()
//                ->regex('/^[\p{L}\s]+$/u') // فقط حروف فارسی/انگلیسی و فاصله
//                ->minLength(3)
//                ->maxLength(255)
//                ->label('نام کامل')
//                ->placeholder('علی محمدی'),
//
//            Forms\Components\TextInput::make('email')
//                ->email()
//                ->required()
//                ->unique(ignoreRecord: true)
//                ->label('ایمیل')
//                ->placeholder('example@domain.com'),
//
//            Forms\Components\TextInput::make('phone')
//                ->tel()
//                ->required()
//                ->unique(ignoreRecord: true)
//                ->regex('/^09[0-9]{9}$/') // شماره ایرانی: 09123456789
//                ->label('تلفن')
//                ->placeholder('09123456789')
//                ->helperText('شماره موبایل را با فرمت 09 وارد کنید'),
//        ]);
//    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('شناسه')
                    ->sortable(),



                Tables\Columns\TextColumn::make('email')
                    ->label('ایمیل')
                    ->searchable()
                    ->sortable(),

//                Tables\Columns\TextColumn::make('phone')
//                    ->label('تلفن')
//                    ->searchable()
//                    ->sortable(),



            ])
            ->defaultSort('id', 'desc') // 🔹 این خط باعث میشه از آخر به اول مرتب بشه
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
        ->headerActions([

        Action::make('export_waiting')
            ->label('Export: لیست انتظار')
            ->icon('heroicon-s-arrow-down-tray')
            ->action(function () {
                return Excel::download(
                    new UsersExport('waitinglist'),
                    'users_waiting.xlsx'
                );
            })
            ->requiresConfirmation(),
    ]);

    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaitingLists::route('/'),
//            'create' => Pages\CreateWaitingList::route('/create'),
            'edit' => Pages\EditWaitingList::route('/{record}/edit'),
        ];
    }
}
