<?php

namespace Dashed\DashedPopups\Commands;

use Illuminate\Console\Command;
use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedPopups\Services\PopupOrderMatcher;

class BackfillPopupOrderMatchesCommand extends Command
{
    protected $signature = 'dashed-popups:backfill-order-matches {--chunk=500 : Aantal records per batch}';

    protected $description = 'Koppel bestaande popup-conversies aan betaalde orders binnen 30 dagen.';

    public function handle(PopupOrderMatcher $matcher): int
    {
        if (! class_exists(\Dashed\DashedEcommerceCore\Models\Order::class)) {
            $this->error('dashed-ecommerce-core is niet geïnstalleerd; matching is niet mogelijk.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $totalMatched = 0;
        $totalScanned = 0;

        PopupView::query()
            ->whereNotNull('submitted_at')
            ->whereNull('matched_order_id')
            ->orderBy('id')
            ->chunkById($chunk, function ($views) use ($matcher, &$totalMatched, &$totalScanned) {
                foreach ($views as $view) {
                    $totalScanned++;
                    if ($matcher->matchView($view) !== null) {
                        $totalMatched++;
                    }
                }
                $this->line("Gescand: {$totalScanned}, gematcht: {$totalMatched}");
            });

        $this->info("Klaar. {$totalMatched} conversies gekoppeld aan een order.");

        return self::SUCCESS;
    }
}
