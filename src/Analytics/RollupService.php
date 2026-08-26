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
        $nu = Carbon::now()->toDateTimeString();

        // SQLite kent TIMESTAMPDIFF niet, en niet als ontbrekende functie: hij
        // leest het eerste argument (MICROSECOND) als kolomnaam en klapt daar
        // al op, voor hij ook maar aan de functie zelf toekomt. Dit pad
        // bestaat uitsluitend voor de testsuite, die in-memory sqlite draait
        // (zie PruneRunner/RollupService-tests in tests/Feature/Retention).
        // Productie draait altijd MySQL en gebruikt de andere tak.
        //
        // De twee takken geven alleen hetzelfde getal omdat first_seen_at en
        // closed_at dateTime-kolommen zijn die uitsluitend met now() gevuld
        // worden, dus zonder fracties van een seconde: TIMESTAMPDIFF(MICRO-
        // SECOND, ...) / 1000 en (strftime('%s', ...) - strftime('%s', ...))
        // * 1000 rekenen dan op seconden-precisie hetzelfde uit. Krijgt een
        // van beide kolommen ooit sub-seconde precisie (een migratie naar
        // timestamp(3) of hoger, of een schrijfpad dat geen now() gebruikt),
        // dan lopen deze twee takken uiteen en merkt geen test dat: alleen de
        // sqlite-tak draait in de testsuite.
        $verschilInMs = fn (string $van, string $tot) => DB::connection()->getDriverName() === 'sqlite'
            ? "(strftime('%s', {$tot}) - strftime('%s', {$van})) * 1000"
            : "TIMESTAMPDIFF(MICROSECOND, {$van}, {$tot}) / 1000";

        $verschilSluiten = $verschilInMs('first_seen_at', 'closed_at');
        $verschilInzenden = $verschilInMs('first_seen_at', 'submitted_at');

        DB::transaction(function () use ($popupId, $dateStr, $start, $end, $bounceMs, $nu, $verschilSluiten, $verschilInzenden) {
            DB::table('dashed__popup_stats_daily')
                ->where('popup_id', $popupId)
                ->whereDate('date', $dateStr)
                ->delete();

            // first_seen_at >= ? AND first_seen_at < ? (range filter) lets MySQL
            // use the (popup_id, first_seen_at) index. DATE(first_seen_at) = ?
            // forced a function call per row and turned this into a full scan
            // of all rows for the popup (millions on large installs).
            DB::statement("
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
                              AND {$verschilSluiten} < ?
                           THEN 1 ELSE 0 END) AS bounces,
                  COALESCE(SUM(CASE WHEN closed_at IS NOT NULL
                                     THEN {$verschilSluiten}
                                     ELSE 0 END), 0) AS sum_ttc,
                  COALESCE(SUM(CASE WHEN submitted_at IS NOT NULL
                                     THEN {$verschilInzenden}
                                     ELSE 0 END), 0) AS sum_tts,
                  ?, ?
                FROM dashed__popup_views
                WHERE popup_id = ?
                  AND first_seen_at >= ?
                  AND first_seen_at < ?
                GROUP BY popup_id, `date`, device_type, triggered_by
            ", [$bounceMs, $nu, $nu, $popupId, $start, $end]);
        });
    }

    /**
     * Aggregeert elke dag met vertoningen ouder dan de grens die nog niet in
     * dashed__popup_stats_daily staat. Zonder deze stap verdwijnen de cijfers
     * met de rijen mee, want de nachtelijke rollup kijkt maar zeven dagen
     * terug en een installatie waar de scheduler stilstond heeft gaten.
     *
     * @return int aantal aangevulde popup-dagcombinaties
     */
    public function zorgVoorDekkingTot(CarbonInterface $grens): int
    {
        // Filteren op created_at en groeperen op first_seen_at is met opzet:
        // verwijderd wordt er op created_at, geaggregeerd op first_seen_at.
        // Zo krijgt elke dag die rijen kwijtraakt gegarandeerd zijn
        // aggregatie, ook als de twee kolommen ooit uiteenlopen.
        $dagen = DB::table('dashed__popup_views')
            ->where('created_at', '<', $grens)
            ->selectRaw('popup_id, DATE(first_seen_at) as dag')
            ->groupBy('popup_id', 'dag')
            ->get();

        $aangevuld = 0;

        foreach ($dagen as $rij) {
            $bestaat = DB::table('dashed__popup_stats_daily')
                ->where('popup_id', $rij->popup_id)
                ->whereDate('date', $rij->dag)
                ->exists();

            if ($bestaat) {
                continue;
            }

            $this->forDay((int) $rij->popup_id, Carbon::parse($rij->dag));
            $aangevuld++;
        }

        return $aangevuld;
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
