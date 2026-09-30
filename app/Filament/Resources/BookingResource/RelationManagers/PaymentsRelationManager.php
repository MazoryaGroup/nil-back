<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Select::make('client_id')
                ->label('Client')
                ->options(
                    Client::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(function (Client $client) {
                            return [
                                $client->id => ($client->name ?: 'No Name')
                                    . ' - '
                                    . $client->phone,
                            ];
                        })
                        ->toArray()
                )
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('amount')
                ->label('Amount')
                ->numeric()
                ->minValue(0)
                ->required(),

            Forms\Components\Select::make('type')
                ->label('Payment Type')
                ->options([
                    'deposit' => 'Deposit',
                    'remaining' => 'Remaining',
                    'refund' => 'Refund',
                ])
                ->required()
                ->default('deposit'),

            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'failed' => 'Failed',
                    'refunded' => 'Refunded',
                ])
                ->required()
                ->default('pending'),

            Forms\Components\TextInput::make('gateway')
                ->label('Gateway')
                ->maxLength(100),

            Forms\Components\TextInput::make('transaction_id')
                ->label('Transaction ID')
                ->maxLength(255),

            Forms\Components\DateTimePicker::make('paid_at')
                ->label('Paid At')
                ->seconds(false),

        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->formatStateUsing(
                        fn ($state, $record) =>
                        $state ?: 'No Name'
                    )
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('IRR')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'deposit',
                        'success' => 'remaining',
                        'danger' => 'refund',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),

                Tables\Columns\TextColumn::make('gateway')
                    ->label('Gateway'),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->limit(25),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Payment'),
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
