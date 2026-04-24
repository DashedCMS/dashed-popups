<?php

namespace Dashed\DashedPopups\Analytics;

use Carbon\CarbonInterface;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedPopups\Models\PopupVariant;
use Dashed\DashedPopups\Models\PopupView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MetricsResolver
{
    public function __construct(private readonly RollupService $rollup) {}

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
            'by_variant' => $this->byVariant($popupId, $from, $to),

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
            'cart_applied' => $this->cartApplied($popupId, $from, $to),
            'revenue' => $redemption['revenue'],
            'discount_value' => $redemption['discount_value'],
            'net_revenue' => $redemption['net_revenue'],
            'redemption_rate' => $submits > 0 ? $redemption['redemptions'] / $submits : 0.0,
        ];
    }

    public function forPopupVariant(int $variantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $views = PopupView::query()
            ->where('variant_id', $variantId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $submits = PopupView::query()
            ->where('variant_id', $variantId)
            ->whereNotNull('submitted_at')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $redemption = DB::table('dashed__orders as o')
            ->join('dashed__popup_views as v', 'v.discount_code_id', '=', 'o.discount_code_id')
            ->where('v.variant_id', $variantId)
            ->whereIn('o.status', ['paid', 'partially_paid', 'waiting_for_confirmation'])
            ->whereBetween('o.created_at', [$from, $to])
            ->selectRaw('COUNT(DISTINCT o.id) as redemptions, COALESCE(SUM(o.total), 0) as revenue')
            ->first();

        $conversionRate = $views > 0 ? round(($submits / $views) * 100, 1) : 0.0;
        $redemptionRate = $submits > 0 ? round(((int) $redemption->redemptions / $submits) * 100, 1) : 0.0;

        return [
            'views' => $views,
            'submits' => $submits,
            'conversion_rate' => $conversionRate,
            'redemptions' => (int) $redemption->redemptions,
            'revenue' => (float) $redemption->revenue,
            'redemption_rate' => $redemptionRate,
        ];
    }

    /**
     * Breakdown popup metrics per dimension bucket.
     *
     * Returned objects carry: key, views, submits, redemptions, revenue,
     * discount_value, net_revenue. Buckets with views but no orders are
     * included with zeroed revenue figures.
     *
     * Note on cross-bucket double-count: redemptions/revenue are derived
     * from an orders x popup_views join on discount_code_id. If two views
     * with different dimension values happen to share the same
     * discount_code_id, the matching order is counted in BOTH buckets.
     * In practice discount codes are minted per-view, so this is rare.
     */
    public function breakdownBy(
        int $popupId,
        string $dimension,
        CarbonInterface $from,
        CarbonInterface $to
    ): Collection {
        $allowed = ['url', 'device_type', 'locale', 'referrer_domain'];
        if (! in_array($dimension, $allowed, true)) {
            throw new \InvalidArgumentException("Unsupported dimension: {$dimension}");
        }

        $column = $dimension === 'referrer_domain' ? 'referrer' : $dimension;

        $viewRows = PopupView::query()
            ->where('popup_id', $popupId)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['id', 'submitted_at', $column]);

        $viewAgg = $viewRows
            ->groupBy(fn ($v) => $this->normalizeDimensionValue($dimension, $v->{$column} ?? ''))
            ->map(fn ($group) => (object) [
                'views' => $group->count(),
                'submits' => $group->whereNotNull('submitted_at')->count(),
            ]);

        $revenueRows = DB::table('dashed__orders as o')
            ->join('dashed__popup_views as pv', 'pv.discount_code_id', '=', 'o.discount_code_id')
            ->where('pv.popup_id', $popupId)
            ->whereNotNull('pv.discount_code_id')
            ->whereIn('o.status', ['paid', 'partially_paid', 'waiting_for_confirmation'])
            ->whereNotIn('o.invoice_id', ['PROFORMA', 'RETURN'])
            ->whereBetween('o.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("pv.{$column} as dim_value, o.id as order_id, o.total, o.discount")
            ->get();

        $revenueAgg = collect($revenueRows)
            ->groupBy(fn ($r) => $this->normalizeDimensionValue($dimension, $r->dim_value ?? ''))
            ->map(function ($group) {
                $unique = $group->unique('order_id');
                $revenue = (float) $unique->sum('total');
                $discount = (float) $unique->sum('discount');

                return (object) [
                    'redemptions' => $unique->count(),
                    'revenue' => $revenue,
                    'discount_value' => $discount,
                    'net_revenue' => $revenue - $discount,
                ];
            });

        $keys = $viewAgg->keys()->merge($revenueAgg->keys())->unique();

        return $keys->map(function ($key) use ($viewAgg, $revenueAgg) {
            $v = $viewAgg->get($key);
            $r = $revenueAgg->get($key);

            return (object) [
                'key' => $key,
                'views' => $v->views ?? 0,
                'submits' => $v->submits ?? 0,
                'redemptions' => $r->redemptions ?? 0,
                'revenue' => $r->revenue ?? 0.0,
                'discount_value' => $r->discount_value ?? 0.0,
                'net_revenue' => $r->net_revenue ?? 0.0,
            ];
        })->sortByDesc('revenue')->values();
    }

    protected function normalizeDimensionValue(string $dimension, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '(geen)';
        }

        return match ($dimension) {
            'url' => $this->normalizeUrl($value),
            'referrer_domain' => $this->extractDomain($value),
            default => $value,
        };
    }

    protected function normalizeUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            $path = '/';
        }
        $supportedLocales = (array) config('app.supported_locales', []);
        $segments = explode('/', ltrim($path, '/'));
        if (count($segments) && in_array($segments[0], $supportedLocales, true)) {
            array_shift($segments);
        }

        return '/'.implode('/', $segments);
    }

    protected function extractDomain(string $referrer): string
    {
        $host = parse_url($referrer, PHP_URL_HOST);

        return $host ?: '(onbekend)';
    }

    private function byVariant(int $popupId, CarbonInterface $from, CarbonInterface $to): array
    {
        $variants = PopupVariant::query()
            ->where('popup_id', $popupId)
            ->orderBy('sort_order')
            ->get();

        return $variants->map(function ($variant) use ($from, $to) {
            $metrics = $this->forPopupVariant($variant->id, $from, $to);

            return [
                'id' => $variant->id,
                'name' => $variant->name,
                'code_prefix' => $variant->code_prefix,
                'enabled' => $variant->enabled,
                'split_weight' => $variant->split_weight,
                ...$metrics,
            ];
        })->all();
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

    private function cartApplied(int $popupId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) Cart::query()
            ->whereNotNull('discount_code_id')
            ->whereIn('discount_code_id', function ($query) use ($popupId, $from, $to) {
                $query->select('discount_code_id')
                    ->from('dashed__popup_views')
                    ->where('popup_id', $popupId)
                    ->whereNotNull('discount_code_id')
                    ->whereBetween('created_at', [$from, $to]);
            })
            ->distinct()
            ->count('id');
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
