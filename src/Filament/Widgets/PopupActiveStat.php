<?php

namespace Dashed\DashedPopups\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Filament\Resources\PopupResource;

/**
 * Stat-widget bovenaan de popups-lijst: telt actieve popups. De Popup-tabel
 * gebruikt kolom "active" (boolean). Klik leidt door naar de is_active-filter.
 */
class PopupActiveStat extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getStats(): array
    {
        $count = Popup::query()
            ->where('active', true)
            ->count();

        return [
            Stat::make('Actieve popups', (string) $count)
                ->color('success')
                ->url(ResourceFilterUrl::for(PopupResource::class, [
                    'is_active' => 1,
                ])),
        ];
    }
}
