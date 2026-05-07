<?php

namespace Dashed\DashedPopups\Services\Summary;

use Dashed\DashedCore\Services\Summary\Contracts\SummaryContributorInterface;
use Dashed\DashedCore\Services\Summary\DTOs\SummaryPeriod;
use Dashed\DashedCore\Services\Summary\DTOs\SummarySection;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Models\PopupView;

/**
 * Bouwt de popup-sectie van de admin samenvatting-mail. Toont totaal
 * aantal views, submits en submit-rate over de periode, plus een tabel
 * met de top 5 popups op aantal submits met hun order-conversie en de
 * via popup-attributie gekoppelde omzet.
 */
class PopupSummaryContributor implements SummaryContributorInterface
{
    public static function key(): string
    {
        return 'popups';
    }

    public static function label(): string
    {
        return 'Popups';
    }

    public static function description(): string
    {
        return 'Views, submits en submit-rate per popup in de periode, plus de top 5 op aantal submits met conversie en attributed omzet uit popup-orders.';
    }

    public static function defaultFrequency(): string
    {
        return 'weekly';
    }

    /**
     * @return array<int, string>
     */
    public static function availableFrequencies(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public static function contribute(SummaryPeriod $period): ?SummarySection
    {
        // Totaal aantal views in de periode (via created_at).
        $totalViews = PopupView::query()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();

        if ($totalViews === 0) {
            return null;
        }

        // Totaal aantal submits in de periode (op submitted_at, niet op
        // created_at, omdat een view en de bijbehorende submit op
        // verschillende dagen kunnen vallen).
        $totalSubmits = PopupView::query()
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$period->start, $period->end])
            ->count();

        $submitRate = $totalViews > 0
            ? round(($totalSubmits / $totalViews) * 100, 1)
            : 0.0;

        $blocks = [];

        $blocks[] = [
            'type' => 'stats',
            'data' => [
                'rows' => [
                    [
                        'label' => 'Views',
                        'value' => number_format($totalViews, 0, ',', '.'),
                    ],
                    [
                        'label' => 'Submits',
                        'value' => number_format($totalSubmits, 0, ',', '.'),
                    ],
                    [
                        'label' => 'Submit-rate',
                        'value' => static::formatPercentage($submitRate),
                    ],
                ],
            ],
        ];

        // Top 5 popups op aantal submits in de periode. Skip popups
        // zonder submits zodat de tabel niet vol komt te staan met
        // lege rijen wanneer een popup wel views had maar geen
        // conversies.
        $topPopups = PopupView::query()
            ->selectRaw('popup_id, COUNT(*) as submits_count')
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$period->start, $period->end])
            ->groupBy('popup_id')
            ->orderByDesc('submits_count')
            ->limit(5)
            ->get();

        $rows = [];

        $orderClass = class_exists(\Dashed\DashedEcommerceCore\Models\Order::class)
            ? \Dashed\DashedEcommerceCore\Models\Order::class
            : null;

        foreach ($topPopups as $row) {
            $popupId = (int) $row->popup_id;
            $submitsCount = (int) $row->submits_count;

            if ($submitsCount === 0) {
                continue;
            }

            $popup = Popup::find($popupId);
            $popupName = $popup?->getTranslation('title', app()->getLocale(), false)
                ?: ($popup?->name ?? 'Popup #'.$popupId);

            // Views per popup in dezelfde periode (op created_at).
            $popupViews = PopupView::query()
                ->where('popup_id', $popupId)
                ->whereBetween('created_at', [$period->start, $period->end])
                ->count();

            $popupSubmitRate = $popupViews > 0
                ? round(($submitsCount / $popupViews) * 100, 1)
                : 0.0;

            // Order-conversie = aantal popup-views met matched_order_id
            // voor deze popup in de periode (op submitted_at, omdat de
            // attributie hangt aan een submit).
            $matchedOrderIds = PopupView::query()
                ->where('popup_id', $popupId)
                ->whereNotNull('matched_order_id')
                ->whereBetween('submitted_at', [$period->start, $period->end])
                ->pluck('matched_order_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $matchedCount = count($matchedOrderIds);

            $revenue = 0.0;
            if ($orderClass && $matchedCount > 0) {
                $revenue = (float) $orderClass::query()
                    ->whereIn('id', $matchedOrderIds)
                    ->sum('total');
            }

            $rows[] = [
                $popupName,
                number_format($popupViews, 0, ',', '.'),
                number_format($submitsCount, 0, ',', '.'),
                static::formatPercentage($popupSubmitRate),
                number_format($matchedCount, 0, ',', '.'),
                static::formatRevenue($revenue),
            ];
        }

        if (! empty($rows)) {
            $blocks[] = [
                'type' => 'heading',
                'data' => [
                    'text' => 'Top 5 popups op submits',
                    'level' => 3,
                ],
            ];

            $blocks[] = [
                'type' => 'table',
                'data' => [
                    'headers' => [
                        'Popup',
                        'Views',
                        'Submits',
                        'Submit %',
                        'Order conversie',
                        'Omzet',
                    ],
                    'rows' => $rows,
                ],
            ];
        }

        return new SummarySection(
            title: 'Popup statistieken',
            blocks: $blocks,
        );
    }

    /**
     * Formatteert een percentage met NL-notatie (komma als decimaal).
     */
    protected static function formatPercentage(float $value): string
    {
        return number_format($value, 1, ',', '.').'%';
    }

    /**
     * Formatteert een omzet-bedrag in euro met NL-notatie.
     */
    protected static function formatRevenue(float $value): string
    {
        return '€ '.number_format($value, 2, ',', '.');
    }
}
