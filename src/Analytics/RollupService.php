<?php

namespace Dashed\DashedPopups\Analytics;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RollupService
{
    public function forDay(int $popupId, CarbonInterface $date): void
    {
        $bounceMs = (int) config('popups.analytics.bounce_threshold_ms', 2000);
        $dateStr = $date->toDateString();
        $start = $date->copy()->startOfDay()->toDateTimeString();
        $end = $date->copy()->addDay()->startOfDay()->toDateTimeString();

        DB::transaction(function () use ($popupId, $dateStr, $start, $end, $bounceMs) {
            DB::table('dashed__popup_stats_daily')
                ->where('popup_id', $popupId)
                ->whereDate('date', $dateStr)
                ->delete();

            // first_seen_at >= ? AND first_seen_at < ? (range filter) lets MySQL
            // use the (popup_id, first_seen_at) index. DATE(first_seen_at) = ?
            // forced a function call per row and turned this into a full scan
            // of all rows for the popup (millions on large installs).
            DB::statement(<<<'SQL'
                INSERT INTO dashed__popup_stats_daily
                  (popup_id, date, device_type, triggered_by,
                   views, submits, dismissals, bounces,
                   sum_time_to_close_ms, sum_time_to_submit_ms,
                   created_at, updated_at)
                SELECT
                  popup_id,
                  DATE(first_seen_at) AS `date`,
                  device_type,
                  triggered_by,
                  COUNT(*) AS views,
                  SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS submits,
                  SUM(CASE WHEN closed_at IS NOT NULL AND submitted_at IS NULL THEN 1 ELSE 0 END) AS dismissals,
                  SUM(CASE WHEN closed_at IS NOT NULL AND submitted_at IS NULL
                              AND TIMESTAMPDIFF(MICROSECOND, first_seen_at, closed_at) / 1000 < ?
                           THEN 1 ELSE 0 END) AS bounces,
                  COALESCE(SUM(CASE WHEN closed_at IS NOT NULL
                                     THEN TIMESTAMPDIFF(MICROSECOND, first_seen_at, closed_at) / 1000
                                     ELSE 0 END), 0) AS sum_ttc,
                  COALESCE(SUM(CASE WHEN submitted_at IS NOT NULL
                                     THEN TIMESTAMPDIFF(MICROSECOND, first_seen_at, submitted_at) / 1000
                                     ELSE 0 END), 0) AS sum_tts,
                  NOW(), NOW()
                FROM dashed__popup_views
                WHERE popup_id = ?
                  AND first_seen_at >= ?
                  AND first_seen_at < ?
                GROUP BY popup_id, `date`, device_type, triggered_by
            SQL, [$bounceMs, $popupId, $start, $end]);
        });
    }

    /**
     * @return Collection<int, object>
     */
    public function forPopup(int $popupId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $today = Carbon::today();
        $includesToday = $to->isSameDay($today) || $to->greaterThanOrEqualTo($today);

        if ($includesToday) {
            Cache::remember(
                "popup-stats-today:{$popupId}",
                (int) config('popups.analytics.today_cache_seconds', 300),
                function () use ($popupId, $today) {
                    $this->forDay($popupId, $today);

                    return true;
                }
            );
        }

        return DB::table('dashed__popup_stats_daily')
            ->where('popup_id', $popupId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();
    }
}
