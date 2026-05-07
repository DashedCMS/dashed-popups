<?php

namespace Dashed\DashedPopups\Filament\Widgets;

use Dashed\DashedPopups\Models\Popup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedPopups\Analytics\MetricsResolver;

class PopupFunnelWidget extends StatsOverviewWidget
{
    public ?Popup $record = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        // forPopup() runs a handful of queries against the (potentially huge)
        // dashed__popup_views table. Caching for 5 minutes is enough to keep
        // the popup edit page responsive — the same TTL the list page uses.
        $metrics = Cache::remember(
            "popup-funnel:{$this->record->id}",
            300,
            fn () => app(MetricsResolver::class)->forPopup(
                $this->record->id,
                now()->subDays(30),
                now(),
            ),
        );

        $views = (int) ($metrics['views'] ?? 0);
        $submits = (int) ($metrics['submits'] ?? 0);
        $cartApplied = (int) ($metrics['cart_applied'] ?? 0);
        $redemptions = (int) ($metrics['redemptions'] ?? 0);

        $submitRate = $views > 0 ? round(($submits / $views) * 100, 1) : 0.0;
        $cartRate = $submits > 0 ? round(($cartApplied / $submits) * 100, 1) : 0.0;
        $conversionRate = $cartApplied > 0 ? round(($redemptions / $cartApplied) * 100, 1) : 0.0;
        $overallRate = $views > 0 ? round(($redemptions / $views) * 100, 1) : 0.0;

        return [
            Stat::make('Views', number_format($views, 0, ',', '.'))
                ->description('Popup getoond (30 dagen)')
                ->icon('heroicon-o-eye')
                ->color('gray'),
            Stat::make('Submits', number_format($submits, 0, ',', '.'))
                ->description($this->formatRate($submitRate, 'van views'))
                ->icon('heroicon-o-envelope')
                ->color('info'),
            Stat::make('In winkelwagen', number_format($cartApplied, 0, ',', '.'))
                ->description($this->formatRate($cartRate, 'van submits'))
                ->icon('heroicon-o-shopping-cart')
                ->color('warning'),
            Stat::make('Conversies', number_format($redemptions, 0, ',', '.'))
                ->description($this->formatRate($conversionRate, 'van in winkelwagen').' / '.$this->formatRate($overallRate, 'overall'))
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }

    private function formatRate(float $rate, string $suffix): string
    {
        return number_format($rate, 1, ',', '.').'% '.$suffix;
    }
}
