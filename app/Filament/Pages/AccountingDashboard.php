<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountingStats;
use Filament\Pages\Page;

class AccountingDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'حسابداری';

    protected static ?string $title = 'داشبورد حسابداری';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.accounting-dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            AccountingStats::class,
        ];
    }
}
