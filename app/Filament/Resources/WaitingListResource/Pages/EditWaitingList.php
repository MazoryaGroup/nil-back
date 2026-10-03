<?php

namespace App\Filament\Resources\WaitingListResource\Pages;

use App\Filament\Resources\WaitingListResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaitingList extends EditRecord
{
    protected static string $resource = WaitingListResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
