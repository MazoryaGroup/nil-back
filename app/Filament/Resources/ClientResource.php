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
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\ClientResource\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\ClientResource\RelationManagers\BookingPaymentsRelationManager;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'مشتری ';
    protected static ?string $pluralLabel = 'کاربران';


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
                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('referral_code')
                    ->label('کد معرفی')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('ایمیل')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('referrer.name')
                    ->label('معرف')
                    ->default('-')
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
                Tables\Actions\ViewAction::make()
                    ->label('مشاهده پرونده')
                    ->icon('heroicon-o-document-text')
                    ->color('primary'),

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


            ;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('مشخصات مشتری')
                    ->schema([
                        TextEntry::make('name')
                            ->label('نام مشتری')
                            ->default('-'),

                        TextEntry::make('phone')
                            ->label('شماره موبایل')
                            ->copyable(),

                        TextEntry::make('email')
                            ->label('ایمیل')
                            ->default('-'),

                        TextEntry::make('referral_code')
                            ->label('کد معرفی')
                            ->copyable()
                            ->default('-'),

                        TextEntry::make('referrer.name')
                            ->label('معرف')
                            ->default('-'),

                        TextEntry::make('created_at')
                            ->label('تاریخ عضویت')
                            ->dateTime('Y/m/d H:i'),
                    ])
                    ->columns(2),

                Section::make('خلاصه فعالیت مشتری')
                    ->schema([
                        TextEntry::make('bookings_count')
                            ->label('تعداد کل رزروها')
                            ->state(
                                fn ($record) =>
                                $record->bookings()->count()
                            )
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('completed_bookings_count')
                            ->label('رزروهای تکمیل‌شده')
                            ->state(
                                fn ($record) =>
                                $record->bookings()
                                    ->where('status', 'completed')
                                    ->count()
                            )
                            ->badge()
                            ->color('success'),

                        TextEntry::make('cancelled_bookings_count')
                            ->label('رزروهای لغوشده')
                            ->state(
                                fn ($record) =>
                                $record->bookings()
                                    ->where('status', 'cancelled')
                                    ->count()
                            )
                            ->badge()
                            ->color('danger'),
                    ])
                    ->columns(3),

                Section::make('خلاصه مالی مشتری')
                    ->description('گزارش مالی بر اساس رزروها و تراکنش‌های ثبت‌شده')
                    ->schema([

                        TextEntry::make('financial_total_bookings')
                            ->label('مجموع مبلغ رزروهای غیرلغوشده')
                            ->state(fn (Client $record) =>
                            $record->bookings()
                                ->whereNotIn('status', [
                                    'cancelled',
                                    'rejected',
                                ])
                                ->sum('total_amount')
                            )
                            ->formatStateUsing(fn ($state) =>
                                number_format((float) $state) . ' تومان'
                            ),

                        TextEntry::make('financial_total_paid')
                            ->label('مجموع پرداخت‌های موفق')
                            ->state(fn (Client $record) =>
                            $record->bookingPayments()
                                ->where('payments.status', 'paid')
                                ->whereIn('payments.type', [
                                    'deposit',
                                    'remaining',
                                ])
                                ->sum('payments.amount')
                            )
                            ->formatStateUsing(fn ($state) =>
                                number_format((float) $state) . ' تومان'
                            ),

                        TextEntry::make('financial_total_refunded')
                            ->label('مجموع بازگشت وجه')
                            ->state(fn (Client $record) =>
                            $record->bookingPayments()
                                ->where('payments.type', 'refund')
                                ->whereIn('payments.status', [
                                    'paid',
                                    'refunded',
                                ])
                                ->sum('payments.amount')
                            )
                            ->formatStateUsing(fn ($state) =>
                                number_format((float) $state) . ' تومان'
                            ),

                        TextEntry::make('financial_net_paid')
                            ->label('خالص پرداختی مشتری')
                            ->state(function (Client $record) {

                                $paid = (float) $record->bookingPayments()
                                    ->where('payments.status', 'paid')
                                    ->whereIn('payments.type', [
                                        'deposit',
                                        'remaining',
                                    ])
                                    ->sum('payments.amount');

                                $refunded = (float) $record->bookingPayments()
                                    ->where('payments.type', 'refund')
                                    ->whereIn('payments.status', [
                                        'paid',
                                        'refunded',
                                    ])
                                    ->sum('payments.amount');

                                return max(0, $paid - $refunded);
                            })
                            ->formatStateUsing(fn ($state) =>
                                number_format((float) $state) . ' تومان'
                            )
                            ->color('success'),

                        TextEntry::make('financial_remaining')
                            ->label('مانده تسویه‌نشده رزروهای غیرلغوشده')
                            ->state(function (Client $record) {

                                return $record->bookings()
                                    ->whereNotIn('status', [
                                        'cancelled',
                                        'rejected',
                                    ])
                                    ->get([
                                        'total_amount',
                                        'paid_amount',
                                    ])
                                    ->sum(fn ($booking) =>
                                    $booking->remaining_amount
                                    );
                            })
                            ->formatStateUsing(fn ($state) =>
                                number_format((float) $state) . ' تومان'
                            )
                            ->color('warning'),

                        TextEntry::make('financial_completed_services')
                            ->label('تعداد خدمات رزروهای تکمیل‌شده')
                            ->state(fn (Client $record) =>
                            \App\Models\BookingService::query()
                                ->whereHas('booking', fn ($query) =>
                                $query
                                    ->where('client_id', $record->id)
                                    ->where('status', 'completed')
                                )
                                ->count()
                            )
                            ->badge()
                            ->color('primary'),

                    ])
                    ->columns(3),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            BookingsRelationManager::class,
            BookingPaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),

        ];
    }
}
