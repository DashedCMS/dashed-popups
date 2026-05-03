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

        try {
            Mail::to($address)->send(new PopupFollowUpMail($view, $email, $view->locale));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Popup follow-up email failed to send', [
                'popup_view_id' => $view->id,
                'follow_up_email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
