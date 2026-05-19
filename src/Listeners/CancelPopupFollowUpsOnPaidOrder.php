<?php

namespace Dashed\DashedPopups\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedPopups\Models\PopupView;

/**
 * Cancels any pending follow-up emails for a popup view when the matching
 * email address places a paid order. Listens for
 * Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent (typed
 * loosely as object so the package can be loaded without dashed-ecommerce-core).
 *
 * Cancel scope is by email match only. A user with the same id but a
 * different email will NOT have their flow cancelled.
 */
class CancelPopupFollowUpsOnPaidOrder implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function handle(object $event): int
    {
        $order = $event->order ?? null;

        if (! $order) {
            return 0;
        }

        $email = strtolower(trim((string) ($order->email ?? '')));

        if ($email === '') {
            return 0;
        }

        return PopupView::query()
            ->where('email', $email)
            ->whereNotNull('follow_up_started_at')
            ->whereNull('follow_up_cancelled_at')
            ->update(['follow_up_cancelled_at' => now()]);
    }
}
