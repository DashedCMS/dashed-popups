<?php

namespace Dashed\DashedPopups\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedPopups\Models\PopupView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncPopupSubmissionToNewsletterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $popupViewId)
    {
    }

    public function handle(): void
    {
        $view = PopupView::with('popup')->find($this->popupViewId);

        if (! $view || ! $view->email || ! $view->submitted_at) {
            return;
        }

        if ($view->newsletter_synced_at) {
            return;
        }

        $apis = $view->popup?->api_subscriptions ?? [];

        if (! $apis) {
            $view->update(['newsletter_synced_at' => now()]);

            return;
        }

        foreach ($apis as $api) {
            $class = $api['class'] ?? null;
            if (! $class || ! class_exists($class)) {
                continue;
            }

            try {
                $class::dispatch($view, $api);
            } catch (\Throwable $e) {
                report($e);
                Log::warning('Popup newsletter dispatch failed', [
                    'popup_id' => $view->popup_id,
                    'view_id' => $view->id,
                    'api_class' => $class,
                    'list_id' => $api['list_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $view->update(['newsletter_synced_at' => now()]);
    }
}
