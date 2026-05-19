<?php

namespace Dashed\DashedPopups\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedPopups\Services\PopupOrderMatcher;

/**
 * Async wrapper voor PopupOrderMatcher::matchForOrder. De match-query op
 * dashed__popup_views (geen index voor `submitted_at` + `matched_order_id`
 * IS NULL) duurde tot enkele seconden en blokkeerde de POS-checkout-respons.
 * Door 'm naar de queue te verplaatsen ziet de cassière de bevestiging direct.
 */
class MatchPopupViewsToOrderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $orderId)
    {
    }

    public function handle(PopupOrderMatcher $matcher): void
    {
        if (! class_exists(\Dashed\DashedEcommerceCore\Models\Order::class)) {
            return;
        }

        $order = \Dashed\DashedEcommerceCore\Models\Order::find($this->orderId);
        if (! $order) {
            return;
        }

        $matcher->matchForOrder($order);
    }
}
