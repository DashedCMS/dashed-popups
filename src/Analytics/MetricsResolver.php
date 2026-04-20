<?php

namespace Dashed\DashedPopups\Analytics;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MetricsResolver
{
    public function __construct(private readonly RollupService $rollup)
    {
    }

    public function forPopup(int $popupId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->rollup->forPopup($popupId, $from, $to);

        $views = (int) $rows->sum('views');
        $submits = (int) $rows->sum('submits');
        $dismissals = (int) $rows->sum('dismissals');
        $bounces = (int) $rows->sum('bounces');
        $sumTtc = (int) $rows->sum('sum_time_to_close_ms');
        $sumTts = (int) $rows->sum('sum_time_to_submit_ms');
        $closedTotal = $dismissals + $bounces;

        $redemption = $this->redemption($popupId, $from, $to);

        return [
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),

            'views' => $views,
            'submits' => $submits,
            'dismissals' => $dismissals,
            'bounces' => $bounces,

            'conversion_rate' => $views > 0 ? $submits / $views : 0.0,
            'dismissal_rate' => $views > 0 ? $dismissals / $views : 0.0,
            'bounce_rate' => $views > 0 ? $bounces / $views : 0.0,

            'avg_time_to_close' => $closedTotal > 0 ? (int) ($sumTtc / $closedTotal) : null,
            'avg_time_to_submit' => $submits > 0 ? (int) ($sumTts / $submits) : null,

            'trend_7d_vs_30d' => $this->trend($popupId, $to),

            'by_device' => $this->groupedRates($rows, 'device_type'),
            'by_trigger' => $this->groupedRates($rows, 'triggered_by'),

            'top_urls' => $this->topRaw($popupId, $from, $to, 'url'),
            'top_referrers' => $this->topRaw($popupId, $from, $to, 'referrer'),

            'daily_series' => $rows
                ->groupBy(fn ($r) => $r->date)
                ->map(fn ($day, $date) => [
                    'date' => $date,
                    'views' => (int) $day->sum('views'),
                    'submits' => (int) $day->sum('submits'),
                ])
                ->values()
                ->all(),

            'redemptions' => $redemption['redemptions'],
            'revenue' => $redemption['revenue'],
            'discount_value' => $redemption['discount_value'],
            'net_revenue' => $redemption['net_revenue'],
            'redemption_rate' => $submits > 0 ? $redemption['redemptions'] / $submits : 0.0,
        ];
    }

    private function trend(int $popupId, CarbonInterface $to): ?float
    {
        $last7 = $this->rollup->forPopup($popupId, $to->copy()->subDays(6), $to);
        $prior = $this->rollup->forPopup($popupId, $to->copy()->subDays(29), $to->copy()->subDays(7));

        $rate = fn ($rows) => ($v = (int) $rows->sum('views')) > 0
            ? ((int) $rows->sum('submits')) / $v
            : null;

        $a = $rate($last7);
        $b = $rate($prior);
        if ($a === null || $b === null || $b == 0.0) {
            return null;
        }

        return $a / $b - 1.0;
    }

    private function groupedRates($rows, string $key): array
    {
        return $rows
            ->groupBy(fn ($r) => $r->{$key} ?? 'onbekend')
            ->map(function ($group, $k) {
                $v = (int) $group->sum('views');
                $s = (int) $group->sum('submits');

                return [
                    'key' => $k,
                    'views' => $v,
                    'submits' => $s,
                    'conversion_rate' => $v > 0 ? $s / $v : 0.0,
                ];
            })
            ->values()
            ->sortByDesc('views')
            ->values()
            ->all();
    }

    private function topRaw(int $popupId, CarbonInterface $from, CarbonInterface $to, string $column): array
    {
        return DB::table('dashed__popup_views')
            ->select([$column, DB::raw('COUNT(*) AS views'), DB::raw('SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS submits')])
            ->where('popup_id', $popupId)
            ->whereBetween('first_seen_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'value' => $r->{$column},
                'views' => (int) $r->views,
                'submits' => (int) $r->submits,
                'conversion_rate' => $r->views > 0 ? ((int) $r->submits) / ((int) $r->views) : 0.0,
            ])
            ->all();
    }

    private function redemption(int $popupId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('dashed__orders as o')
            ->join('dashed__popup_views as pv', 'pv.discount_code_id', '=', 'o.discount_code_id')
            ->where('pv.popup_id', $popupId)
            ->whereNotNull('pv.discount_code_id')
            ->whereIn('o.status', ['paid', 'partially_paid', 'waiting_for_confirmation'])
            ->whereNotIn('o.invoice_id', ['PROFORMA', 'RETURN'])
            ->whereBetween('o.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('o.id, o.total, o.discount, o.created_at')
            ->groupBy('o.id', 'o.total', 'o.discount', 'o.created_at')
            ->get();

        $revenue = (float) $rows->sum('total');
        $discountValue = (float) $rows->sum('discount');

        return [
            'redemptions' => $rows->count(),
            'revenue' => $revenue,
            'discount_value' => $discountValue,
            'net_revenue' => $revenue - $discountValue,
        ];
    }
}
