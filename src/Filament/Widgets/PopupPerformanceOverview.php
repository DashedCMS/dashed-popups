<?php

namespace Dashed\DashedPopups\Filament\Widgets;

use Dashed\DashedPopups\Models\Popup;
use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedPopups\Analytics\MetricsResolver;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class PopupPerformanceOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Vroege return als er nog geen popups zijn: dan hoeft de widget
        // niet via Cache::remember + MetricsResolver te gaan. Voorkomt
        // dat de overview-pagina blijft laden op een verse installatie.
        $popupIds = Popup::query()->pluck('id');

        if ($popupIds->isEmpty()) {
            return [
                Stat::make('Views (30d)', '0'),
                Stat::make('Submits (30d)', '0'),
                Stat::make('Conversie (30d)', '-'),
                Stat::make('Omzet uit popups (30d)', CurrencyHelper::formatPrice(0)),
            ];
        }

        return Cache::remember('popup-overview-30d', 300, function () use ($popupIds) {
            $resolver = app(MetricsResolver::class);
            $from = now()->subDays(29)->startOfDay();
            $to = now()->endOfDay();

            $totalViews = 0;
            $totalSubmits = 0;
            $totalRevenue = 0.0;

            foreach ($popupIds as $popupId) {
                try {
                    $m = $resolver->forPopup((int) $popupId, $from, $to);
                } catch (\Throwable $e) {
                    report($e);
                    continue;
                }
                $totalViews += $m['views'] ?? 0;
                $totalSubmits += $m['submits'] ?? 0;
                $totalRevenue += $m['revenue'] ?? 0;
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
