<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffResource\Pages;
use App\Helpers\JalaliHelper;
use App\Models\Staff;
use App\Models\Service;
use Ariaieboy\FilamentJalaliDatetimepicker\Forms\Components\JalaliDatePicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'پرسنل';

    protected static ?string $modelLabel = 'پرسنل';

    protected static ?string $pluralModelLabel = 'پرسنل';

    protected static ?string $navigationGroup = 'NIL';

    public static function form(Form $form): Form
    {
        return $form->schema([

            /*
            |--------------------------------------------------------------------------
            | اطلاعات پرسنل
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('اطلاعات پرسنل')
                ->schema([

                    Forms\Components\TextInput::make('name')
                        ->label('نام')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('phone')
                        ->label('شماره تلفن')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\TextInput::make('email')
                        ->label('ایمیل')
                        ->email()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('avatar')
                        ->label('تصویر پروفایل')
                        ->maxLength(500),

                    Forms\Components\Toggle::make('is_active')
                        ->label('فعال')
                        ->default(true),

                ])
                ->columns(2),

            /*
            |--------------------------------------------------------------------------
            | خدمات
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('خدمات')
                ->schema([

                    Forms\Components\Repeater::make('staffServices')
                        ->relationship()
                        ->schema([

                            Forms\Components\Select::make('service_id')
                                ->label('خدمت')
                                ->options(
                                    Service::query()
                                        ->orderBy('id')
                                        ->get()
                                        ->mapWithKeys(function (Service $service) {
                                            return [
                                                $service->id =>
                                                    $service->name
                                                        ?: 'خدمت شماره ' . $service->id,
                                            ];
                                        })
                                        ->toArray()
                                )
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\TextInput::make('duration')
                                ->label('مدت زمان (دقیقه)')
                                ->numeric()
                                ->minValue(1)
                                ->required(),

                            Forms\Components\TextInput::make('price')
                                ->label('قیمت')
                                ->numeric()
                                ->minValue(0)
                                ->required(),

                            Forms\Components\Toggle::make('is_active')
                                ->label('فعال')
                                ->default(true),

                        ])
                        ->columns(4)
                        ->addActionLabel('افزودن خدمت')
                        ->collapsible()
                        ->itemLabel(
                            fn (array $state): ?string =>
                            !empty($state['service_id'])
                                ? (
                                Service::find(
                                    $state['service_id']
                                )?->name
                                ?? 'خدمت شماره '
                            . $state['service_id']
                            )
                                : null
                        ),

                ]),

            /*
            |--------------------------------------------------------------------------
            | برنامه کاری
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('برنامه کاری')
                ->schema([

                    Forms\Components\Repeater::make('schedules')
                        ->relationship()
                        ->schema([

                            Forms\Components\Select::make('day_of_week')
                                ->label('روز')
                                ->options([
                                    0 => 'یکشنبه',
                                    1 => 'دوشنبه',
                                    2 => 'سه‌شنبه',
                                    3 => 'چهارشنبه',
                                    4 => 'پنجشنبه',
                                    5 => 'جمعه',
                                    6 => 'شنبه',
                                ])
                                ->required(),

                            Forms\Components\TimePicker::make('start_time')
                                ->label('شروع')
                                ->seconds(false)
                                ->required(),

                            Forms\Components\TimePicker::make('end_time')
                                ->label('پایان')
                                ->seconds(false)
                                ->required(),

                            Forms\Components\Toggle::make('is_active')
                                ->label('فعال')
                                ->default(true),

                        ])
                        ->columns(4)
                        ->addActionLabel('افزودن برنامه')
                        ->collapsible(),

                ]),

            /*
            |--------------------------------------------------------------------------
            | استراحت‌ها
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('استراحت‌ها')
                ->schema([

                    Forms\Components\Repeater::make('breaks')
                        ->relationship()
                        ->schema([

                            Forms\Components\Select::make('day_of_week')
                                ->label('روز')
                                ->options([
                                    0 => 'یکشنبه',
                                    1 => 'دوشنبه',
                                    2 => 'سه‌شنبه',
                                    3 => 'چهارشنبه',
                                    4 => 'پنجشنبه',
                                    5 => 'جمعه',
                                    6 => 'شنبه',
                                ])
                                ->required(),

                            Forms\Components\TimePicker::make('start_time')
                                ->label('شروع')
                                ->seconds(false)
                                ->required(),

                            Forms\Components\TimePicker::make('end_time')
                                ->label('پایان')
                                ->seconds(false)
                                ->required(),

                            Forms\Components\Toggle::make('is_active')
                                ->label('فعال')
                                ->default(true),

                        ])
                        ->columns(4)
                        ->addActionLabel('افزودن استراحت')
                        ->collapsible(),

                ]),

            /*
            |--------------------------------------------------------------------------
            | مرخصی‌ها
            |--------------------------------------------------------------------------
            */

            Forms\Components\Section::make('مرخصی‌ها')
                ->schema([

                    Forms\Components\Repeater::make('leaves')
                        ->relationship()
                        ->schema([

                            /*
                            |----------------------------------------------------------
                            | تاریخ مرخصی - شمسی
                            |----------------------------------------------------------
                            */

                            JalaliDatePicker::make('leave_date')
                                ->label('تاریخ')
                                ->displayFormat('Y/m/d')
                                ->native(false)
                                ->required(),

                            Forms\Components\TimePicker::make('start_time')
                                ->label('شروع')
                                ->seconds(false),

                            Forms\Components\TimePicker::make('end_time')
                                ->label('پایان')
                                ->seconds(false),

                            Forms\Components\TextInput::make('reason')
                                ->label('دلیل')
                                ->maxLength(500),

                            Forms\Components\Toggle::make('is_active')
                                ->label('فعال')
                                ->default(true),

                        ])
                        ->columns(5)
                        ->addActionLabel('افزودن مرخصی')
                        ->collapsible(),

                ]),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('شماره تلفن')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('ایمیل')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                /*
                |--------------------------------------------------------------------------
                | تاریخ ایجاد - شمسی
                |--------------------------------------------------------------------------
                */

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime(
                                $state,
                                'Y/m/d H:i'
                            )
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
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
