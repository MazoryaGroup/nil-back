<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Models\Service;
use App\Models\Staff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BookingServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'bookingServices';

    protected static ?string $title = 'Booking Services';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Select::make('service_id')
                ->label('Service')
                ->options(
                    Service::query()
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(function (Service $service) {
                            return [
                                $service->id => $service->name
                                    ?: 'Service #' . $service->id,
                            ];
                        })
                        ->toArray()
                )
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Select::make('staff_id')
                ->label('Staff')
                ->options(
                    Staff::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(function (Staff $staff) {
                            return [
                                $staff->id => $staff->name,
                            ];
                        })
                        ->toArray()
                )
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TimePicker::make('start_time')
                ->label('Start Time')
                ->seconds(false)
                ->required(),

            Forms\Components\TimePicker::make('end_time')
                ->label('End Time')
                ->seconds(false)
                ->required(),

            Forms\Components\TextInput::make('duration')
                ->label('Duration (minutes)')
                ->numeric()
                ->minValue(1)
                ->required(),

            Forms\Components\TextInput::make('price')
                ->label('Price')
                ->numeric()
                ->minValue(0)
                ->required()
                ->default(0),

            Forms\Components\TextInput::make('deposit_amount')
                ->label('Deposit')
                ->numeric()
                ->minValue(0)
                ->required()
                ->default(0),

        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->formatStateUsing(
                        fn ($state, $record) =>
                        $state ?: 'Service #' . $record->service_id
                    )
                    ->searchable(),

                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('End'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->suffix(' min'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('IRR'),

                Tables\Columns\TextColumn::make('deposit_amount')
                    ->label('Deposit')
                    ->money('IRR'),

            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Service'),
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
}
