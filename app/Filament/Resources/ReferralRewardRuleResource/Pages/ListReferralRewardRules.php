<?php

namespace App\Filament\Resources\ReferralRewardRuleResource\Pages;

use App\Filament\Resources\ReferralRewardRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReferralRewardRules extends ListRecords
{
    protected static string $resource = ReferralRewardRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
