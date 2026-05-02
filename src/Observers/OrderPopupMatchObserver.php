<?php

namespace Dashed\DashedPopups\Observers;

use Dashed\DashedPopups\Services\PopupOrderMatcher;

class OrderPopupMatchObserver
{
    public function __construct(protected PopupOrderMatcher $matcher)
    {
    }

    public function updated($order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }
        if (! in_array($order->status, ['paid', 'waiting_for_confirmation', 'partially_paid'], true)) {
            return;
        }

        $this->matcher->matchForOrder($order);
    }

    public function created($order): void
    {
        if (! in_array($order->status, ['paid', 'waiting_for_confirmation', 'partially_paid'], true)) {
            return;
        }

        $this->matcher->matchForOrder($order);
    }
}
