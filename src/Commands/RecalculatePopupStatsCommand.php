<?php

declare(strict_types=1);

namespace Dashed\DashedPopups\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Analytics\MetricsResolver;

/**
 * Hertbereken de cached stats-kolommen op `dashed__popups`. Wordt elk uur
 * gedraaid door de scheduler zodat de PopupResource overzichtspagina geen
 * subqueries meer hoeft te draaien per popup. Stats die hier landen:
 *
 *  - all-time: views, submits, dismissals, in_flow (active follow-ups)
 *  - 30-daags: views, submits, dismissals, bounces (via MetricsResolver)
 *  - 30-daags: revenue (via MetricsResolver -> popup-attributed orders)
 *  - stats_recalculated_at = now()
 */
class RecalculatePopupStatsCommand extends Command
{
    protected $signature = 'dashed:recalculate-popup-stats {--popup= : Optionele popup-id om alleen die popup te updaten}';

    protected $description = 'Hertbereken de cached stats-kolommen op alle popups (totalen + 30d).';

    public function handle(MetricsResolver $resolver): int
    {
        $query = Popup::query()->select(['id']);

        if ($onlyPopupId = (int) ($this->option('popup') ?: 0)) {
            $query->where('id', $onlyPopupId);
        }

        $count = 0;
        $errors = 0;

        $query->orderBy('id')->chunk(100, function ($popups) use ($resolver, &$count, &$errors) {
            foreach ($popups as $popup) {
                try {
                    $this->recalculateOne($popup->id, $resolver);
                    $count++;
                } catch (Throwable $e) {
                    report($e);
                    $errors++;
                    $this->warn("Popup {$popup->id} faalde: {$e->getMessage()}");
                }
            }
        });

        $this->info("Stats herberekend voor {$count} popup(s). {$errors} fout(en).");

        return self::SUCCESS;
    }

    private function recalculateOne(int $popupId, MetricsResolver $resolver): void
    {
        // All-time tellers via 1 enkele aggregate-query op popup_views.
        $totals = DB::table('dashed__popup_views')
            ->where('popup_id', $popupId)
            ->selectRaw('
                COUNT(*) as views,
                SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) as submits,
                SUM(CASE WHEN closed_at IS NOT NULL AND submitted_at IS NULL THEN 1 ELSE 0 END) as dismissals,
                SUM(CASE WHEN follow_up_started_at IS NOT NULL AND follow_up_cancelled_at IS NULL THEN 1 ELSE 0 END) as in_flow
            ')
            ->first();

        // 30-daagse stats via de bestaande MetricsResolver (gebruikt
        // dashed__popup_stats_daily zodat er geen full-table-scan ontstaat).
        $from = now()->subDays(29)->startOfDay();
        $to = now()->endOfDay();
        $metrics30d = $resolver->forPopup($popupId, $from, $to);

        Popup::query()->where('id', $popupId)->update([
            'cached_views_count' => (int) ($totals->views ?? 0),
            'cached_submits_count' => (int) ($totals->submits ?? 0),
            'cached_dismissals_count' => (int) ($totals->dismissals ?? 0),
            'cached_in_flow_count' => (int) ($totals->in_flow ?? 0),
            'cached_views_30d' => (int) ($metrics30d['views'] ?? 0),
            'cached_submits_30d' => (int) ($metrics30d['submits'] ?? 0),
            'cached_dismissals_30d' => (int) ($metrics30d['dismissals'] ?? 0),
            'cached_bounces_30d' => (int) ($metrics30d['bounces'] ?? 0),
            'cached_revenue_30d' => (float) ($metrics30d['revenue'] ?? 0),
            'stats_recalculated_at' => now(),
        ]);
    }
}
