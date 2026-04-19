<?php

namespace Dashed\DashedPopups\Commands;

use Carbon\CarbonPeriod;
use Dashed\DashedPopups\Analytics\RollupService;
use Dashed\DashedPopups\Models\Popup;
use Illuminate\Console\Command;

class RollupPopupStatsCommand extends Command
{
    protected $signature = 'popups:rollup-stats {--days=}';

    protected $description = 'Aggregate popup view data into dashed__popup_stats_daily';

    public function handle(RollupService $rollup): int
    {
        $days = (int) ($this->option('days') ?? config('popups.analytics.rollup_days', 7));
        $days = max(0, $days);
        $period = CarbonPeriod::create(now()->subDays($days)->startOfDay(), now()->startOfDay());

        $popups = Popup::query()->select('id')->get();
        if ($popups->isEmpty()) {
            $this->info('No popups to aggregate.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($popups as $popup) {
            foreach ($period as $date) {
                $rollup->forDay($popup->id, $date);
                $total++;
            }
        }

        $this->info("Rolled up {$total} popup-day rows across {$popups->count()} popup(s) for the last {$days} day(s).");

        return self::SUCCESS;
    }
}
