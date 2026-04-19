<?php

namespace Dashed\DashedPopups\Filament\Widgets;

use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedPopups\Analytics\MetricsResolver;
use Dashed\DashedPopups\Models\Popup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PopupPerformanceOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return Cache::remember('popup-overview-30d', 300, function () {
            $resolver = app(MetricsResolver::class);
            $from = now()->subDays(29)->startOfDay();
            $to = now()->endOfDay();

            $totalViews = 0;
            $totalSubmits = 0;
            $totalRevenue = 0.0;

            foreach (Popup::query()->get(['id']) as $popup) {
                $m = $resolver->forPopup($popup->id, $from, $to);
                $totalViews += $m['views'];
                $totalSubmits += $m['submits'];
                $totalRevenue += $m['revenue'];
            }

            $conv = $totalViews > 0 ? number_format($totalSubmits / $totalViews * 100, 2).'%' : '-';

            return [
                Stat::make('Views (30d)', number_format($totalViews)),
                Stat::make('Submits (30d)', number_format($totalSubmits)),
                Stat::make('Conversie (30d)', $conv),
                Stat::make('Omzet uit popups (30d)', CurrencyHelper::formatPrice($totalRevenue)),
            ];
        });
    }
}
