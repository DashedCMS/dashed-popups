<?php

namespace Dashed\DashedPopups\Services;

use Dashed\DashedPopups\Models\PopupView;

class PopupOrderMatcher
{
    /** @var array<string, bool> */
    protected const PAID_STATUSES = [
        'paid' => true,
        'waiting_for_confirmation' => true,
        'partially_paid' => true,
    ];

    /**
     * Try to find a matching paid order for this PopupView. Only writes
     * matched_order_id when it is currently null — matches are one-shot.
     *
     * @return int|null the order id that was matched, or null if no match
     */
    public function matchView(PopupView $view): ?int
    {
        if (! $this->ecommerceAvailable()) {
            return null;
        }
        if ($view->submitted_at === null) {
            return null;
        }
        if ($view->matched_order_id !== null) {
            return $view->matched_order_id;
        }
        if ($view->discount_code_id === null && empty($view->email)) {
            return null;
        }

        $orderClass = \Dashed\DashedEcommerceCore\Models\Order::class;

        $id = $orderClass::query()
            ->whereIn('status', array_keys(self::PAID_STATUSES))
            ->where('created_at', '>', $view->submitted_at)
            ->where('created_at', '<=', $view->submitted_at->copy()->addDays(30))
            ->where(function ($q) use ($view) {
                if ($view->discount_code_id) {
                    $q->orWhere('discount_code_id', $view->discount_code_id);
                }
                if (! empty($view->email)) {
                    $q->orWhere('email', $view->email);
                }
            })
            ->orderBy('created_at', 'asc')
            ->value('id');

        if ($id === null) {
            return null;
        }

        $view->update(['matched_order_id' => $id]);

        return $id;
    }

    /**
     * Called when an order reaches a paid status. Finds PopupViews within the
     * prior 30 days (email or discount-code match) that have no matched order
     * yet and assigns them this order.
     *
     * @param  \Dashed\DashedEcommerceCore\Models\Order  $order
     */
    public function matchForOrder($order): int
    {
        if (! $this->ecommerceAvailable()) {
            return 0;
        }
        if (! in_array($order->status, array_keys(self::PAID_STATUSES), true)) {
            return 0;
        }

        $windowStart = $order->created_at->copy()->subDays(30);

        $candidates = PopupView::query()
            ->whereNotNull('submitted_at')
            ->whereNull('matched_order_id')
            ->where('submitted_at', '>=', $windowStart)
            ->where('submitted_at', '<', $order->created_at)
            ->where(function ($q) use ($order) {
                if ($order->discount_code_id) {
                    $q->orWhere('discount_code_id', $order->discount_code_id);
                }
                if (! empty($order->email)) {
                    $q->orWhere('email', $order->email);
                }
            })
            ->get();

        $count = 0;
        foreach ($candidates as $view) {
            $view->update(['matched_order_id' => $order->id]);
            $count++;
        }

        return $count;
    }

    protected function ecommerceAvailable(): bool
    {
        return class_exists(\Dashed\DashedEcommerceCore\Models\Order::class);
    }
}
