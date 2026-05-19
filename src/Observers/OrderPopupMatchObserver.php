<?php

namespace Dashed\DashedPopups\Observers;

use Dashed\DashedPopups\Jobs\MatchPopupViewsToOrderJob;

class OrderPopupMatchObserver
{
    public function updated($order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }
        if (! in_array($order->status, ['paid', 'waiting_for_confirmation', 'partially_paid'], true)) {
            return;
        }

        MatchPopupViewsToOrderJob::dispatch($order->id);
    }

    public function created($order): void
    {
        if (! in_array($order->status, ['paid', 'waiting_for_confirmation', 'partially_paid'], true)) {
            return;
        }

        MatchPopupViewsToOrderJob::dispatch($order->id);
    }
}
