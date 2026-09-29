<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Models\Address;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'کاربران';
    protected static ?string $pluralLabel = 'کاربران';
    protected static ?string $navigationGroup = 'مدیریت کاربران';

    protected static ?string $slug = 'clients';
    protected static ?string $modelLabel = 'کاربر';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->label('نام')
                    ->maxLength(255),

                Forms\Components\TextInput::make('last_name')
                    ->label('فامیلی')
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('ایمیل')
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('phone')
                    ->label('شماره موبایل')
                    ->required()
                    ->maxLength(11)
                    ->rule('regex:/^09[0-9]{9}$/'),

                Forms\Components\TextInput::make('password')
                    ->label('رمز عبور')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->required(
                        fn ($livewire) =>
                            $livewire instanceof \Filament\Resources\Pages\CreateRecord
                    )
                    ->maxLength(255),

                FileUpload::make('profile_image')
                    ->label('تصویر پروفایل')
                    ->disk('public')
                    ->directory('user-profiles')
                    ->image()
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/jpg',
                        'image/gif'
                    ])
                    ->maxSize(3048),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('نام')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('last_name')
                    ->label('فامیلی')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('ایمیل')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('addresses.city')
                    ->label('شهر')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('شماره موبایل')
                    ->sortable()
                    ->searchable(),


            ])
            ->defaultSort('id', 'desc')

            ->filters([
                //
            ])

            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])

            ->bulkActions([
                BulkAction::make('export_selected')
                    ->label('Export: Selected (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->toArray();

                        return Excel::download(
                            new UsersExport($ids),
                            'users_selected.xlsx'
                        );
                    })
                    ->requiresConfirmation()
                    ->color('secondary'),

                Tables\Actions\DeleteBulkAction::make(),
            ])

            ->headerActions([
                Action::make('export_all')
                    ->label('Export: همه کاربران')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        return Excel::download(
                            new UsersExport('all'),
                            'users_all.xlsx'
                        );
                    })
                    ->requiresConfirmation(),

                Action::make('export_waiting')
                    ->label('Export: لیست انتظار')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        return Excel::download(
                            new UsersExport('waiting'),
                            'users_waiting.xlsx'
                        );
                    })
                    ->requiresConfirmation(),

                Action::make('export_has_order')
                    ->label('Export: کاربران دارای خرید')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        return Excel::download(
                            new UsersExport('has_order'),
                            'users_with_orders.xlsx'
                        );
                    })
                    ->requiresConfirmation(),

                Action::make('export_no_order')
                    ->label('Export: کاربران بدون خرید')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        return Excel::download(
                            new UsersExport('no_order'),
                            'users_without_orders.xlsx'
                        );
                    })
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
