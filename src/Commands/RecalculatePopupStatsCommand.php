<?php

declare(strict_types=1);

namespace Dashed\DashedPopups\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Analytics\RollupService;
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

    /**
     * De ontdekkingsquery in zorgVoorDekkingTot() groepeert over de hele
     * dashed__popup_views zonder popup-filter. Onbegrensd aangeroepen scant
     * die dus elke keer de volledige tabel (zes miljoen rijen in productie),
     * en dit commando draait elk uur via de scheduler. Daarom wordt de
     * ontdekking hier begrensd tot dertig dagen terug: dat heelt gaten van
     * een scheduler die tot een maand heeft stilgestaan, met een bereikscan
     * in plaats van een volledige. Gaten die verder terugliggen worden niet
     * door dit commando geheeld, maar door de dagelijkse vooraf-haak van het
     * opruimen (die de aggregatie bijwerkt voor rijen die de bewaartermijn
     * naderen) en door de eenmalige historische aanvulronde.
     */
    private const DEKKING_VENSTER_DAGEN = 30;

    public function handle(MetricsResolver $resolver, RollupService $rollup): int
    {
        // Eenmalig voor de hele run, niet per popup: de ontdekkingsquery werkt
        // over dashed__popup_views in zijn geheel en niet per popup_id, dus
        // een aanroep per popup zou de scan net zo vaak herhalen zonder dat
        // er na de eerste keer nog iets aan te vullen valt.
        $rollup->zorgVoorDekkingTot(now(), now()->subDays(self::DEKKING_VENSTER_DAGEN));

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
        // All-time tellers uit de dagaggregatie. De ruwe vertoningen worden na
        // hun bewaartermijn opgeruimd; wie de tellers daaruit blijft halen laat
        // ze na drie maanden stilzwijgend inzakken. handle() heeft de
        // aggregatie hierboven al bijgewerkt tot en met vandaag, dus deze som
        // mist geen dagen die nog niet opgeruimd zijn.
        $totalen = DB::table('dashed__popup_stats_daily')
            ->where('popup_id', $popupId)
            ->selectRaw('
                COALESCE(SUM(views), 0) as views,
                COALESCE(SUM(submits), 0) as submits,
                COALESCE(SUM(dismissals), 0) as dismissals
            ')
            ->first();

        // Een lopende follow-up is per definitie recent, dus die telt wel uit
        // de ruwe tabel; bovendien staat hij niet in de dagaggregatie.
        $inFlow = DB::table('dashed__popup_views')
            ->where('popup_id', $popupId)
            ->whereNotNull('follow_up_started_at')
            ->whereNull('follow_up_cancelled_at')
            ->count();

        // 30-daagse stats via de bestaande MetricsResolver (gebruikt
        // dashed__popup_stats_daily zodat er geen full-table-scan ontstaat).
        $from = now()->subDays(29)->startOfDay();
        $to = now()->endOfDay();
        $metrics30d = $resolver->forPopup($popupId, $from, $to);

        // Geen ?? 0 achter $totalen->*: de aggregate hierboven heeft geen
        // GROUP BY, dus first() levert altijd precies één rij met de
        // COALESCE(...)-waarden uit de query, nooit null.
        Popup::query()->where('id', $popupId)->update([
            'cached_views_count' => (int) $totalen->views,
            'cached_submits_count' => (int) $totalen->submits,
            'cached_dismissals_count' => (int) $totalen->dismissals,
            'cached_in_flow_count' => $inFlow,
            'cached_views_30d' => (int) ($metrics30d['views'] ?? 0),
            'cached_submits_30d' => (int) ($metrics30d['submits'] ?? 0),
            'cached_dismissals_30d' => (int) ($metrics30d['dismissals'] ?? 0),
            'cached_bounces_30d' => (int) ($metrics30d['bounces'] ?? 0),
            'cached_revenue_30d' => (float) ($metrics30d['revenue'] ?? 0),
            'stats_recalculated_at' => now(),
        ]);
    }
}
