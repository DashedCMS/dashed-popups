<?php

namespace Dashed\DashedPopups\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedPopups\Models\PopupView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedPopups\Mail\PopupFollowUpMail;
use Dashed\DashedPopups\Models\PopupFollowUpEmail;
use Dashed\DashedPopups\Services\PopupOrderMatcher;

class SendPopupFollowUpEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $popupViewId,
        public int $followUpEmailId,
    ) {
    }

    public function handle(): void
    {
        $view = PopupView::find($this->popupViewId);
        $email = PopupFollowUpEmail::find($this->followUpEmailId);

        if (! $view || ! $email) {
            return;
        }

        if ($view->follow_up_cancelled_at !== null) {
            return;
        }

        if (! $email->is_active) {
            return;
        }

        $address = $view->email;
        if (blank($address)) {
            return;
        }

        // Try to match a recent order to this popup view at send-time so that
        // a fresh conversion (or one missed by the OrderMarkedAsPaidEvent
        // listener) is detected just before we would otherwise send the next
        // step in the flow.
        if ($view->matched_order_id === null && class_exists(PopupOrderMatcher::class)) {
            try {
                app(PopupOrderMatcher::class)->matchView($view);
                $view->refresh();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // If the popup conversion has already produced an order, cancel the
        // remaining follow-up steps and skip this send. The cancellation
        // mirrors the CancelPopupFollowUpsOnPaidOrder listener so any future
        // step that has been queued earlier also stops.
        if ($view->matched_order_id !== null) {
            if ($view->follow_up_cancelled_at === null) {
                $view->update(['follow_up_cancelled_at' => now()]);
            }

            return;
        }

        try {
            Mail::to($address)->send(new PopupFollowUpMail($view, $email, $view->locale));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Popup follow-up email failed to send', [
                'popup_view_id' => $view->id,
                'follow_up_email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            // Postmark/SES e.d. leveren een hard error voor inactive
            // adressen (hard bounce / spam complaint). Verdere stappen
            // voor dezelfde ontvanger zouden ook falen, dus we
            // cancellen de flow en laten de job slagen zodat hij niet
            // eindeloos retried wordt.
            if ($view->follow_up_cancelled_at === null) {
                $view->forceFill(['follow_up_cancelled_at' => now()])->save();
            }
        }
    }
}
