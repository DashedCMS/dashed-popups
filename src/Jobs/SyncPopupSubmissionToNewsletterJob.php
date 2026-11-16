<?php

namespace Dashed\DashedPopups\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedPopups\Models\PopupView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedCore\Models\BackgroundTask;
use Dashed\DashedPopups\Exceptions\NewsletterRateLimitException;

class SyncPopupSubmissionToNewsletterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TASK_TYPE = 'popup-newsletter-sync';

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $popupViewId,
        public bool $force = false,
        public ?int $taskId = null,
    ) {
    }

    public function handle(): void
    {
        $view = PopupView::with('popup')->find($this->popupViewId);

        if (! $view || ! $view->email || ! $view->submitted_at) {
            $this->advanceTask();

            return;
        }

        if (! $this->force && $view->newsletter_synced_at) {
            $this->advanceTask();

            return;
        }

        $apis = $view->popup?->api_subscriptions ?? [];

        if (! $apis) {
            $view->update(['newsletter_synced_at' => now()]);
            $this->advanceTask();

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
        $this->advanceTask();
    }

    /**
     * Ook een aanmelding die na alle pogingen niet doorkwam telt als
     * afgehandeld, anders blijft de taak van een bulkronde eeuwig op bezig.
     */
    public function failed(\Throwable $e): void
    {
        $this->advanceTask();
    }

    /**
     * Alleen bij een bulkronde vanuit het CMS (taskId gevuld): een losse
     * inzending heeft geen taak. Telt in de database op en niet op het model,
     * want de jobs lopen naast elkaar op meerdere workers.
     */
    protected function advanceTask(): void
    {
        if ($this->taskId === null || ! class_exists(BackgroundTask::class)) {
            return;
        }

        rescue(function () {
            BackgroundTask::query()->whereKey($this->taskId)->active()->increment('done');

            $task = BackgroundTask::query()->whereKey($this->taskId)->active()->first();

            if ($task && $task->total && $task->done >= $task->total) {
                $task->finish(__(':aantal aanmeldingen doorgezet.', ['aantal' => $task->total]));
            }
        }, report: false);
    }
}
