<?php

namespace Dashed\DashedPopups\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedPopups\Models\PopupView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedPopups\Exceptions\NewsletterRateLimitException;

class SyncPopupSubmissionToNewsletterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $popupViewId,
        public bool $force = false,
    ) {
    }

    public function handle(): void
    {
        $view = PopupView::with('popup')->find($this->popupViewId);

        if (! $view || ! $view->email || ! $view->submitted_at) {
            return;
        }

        if (! $this->force && $view->newsletter_synced_at) {
            return;
        }

        $apis = $view->popup?->api_subscriptions ?? [];

        if (! $apis) {
            $view->update(['newsletter_synced_at' => now()]);

            return;
        }

        $rateLimitDelay = 0;

        foreach ($apis as $api) {
            $class = $api['class'] ?? null;
            if (! $class || ! class_exists($class)) {
                continue;
            }

            try {
                $class::dispatch($view, $api);
            } catch (NewsletterRateLimitException $e) {
                $rateLimitDelay = max($rateLimitDelay, $e->retryAfter);
                Log::info('Popup newsletter rate limited; will retry job', [
                    'popup_id' => $view->popup_id,
                    'view_id' => $view->id,
                    'api_class' => $class,
                    'list_id' => $api['list_id'] ?? null,
                    'retry_after' => $e->retryAfter,
                ]);
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

        if ($rateLimitDelay > 0) {
            $this->release($rateLimitDelay);

            return;
        }

        $view->update(['newsletter_synced_at' => now()]);
    }
}
