<?php

declare(strict_types=1);

namespace Dashed\DashedPopups\Services;

use Dashed\DashedPopups\Jobs\SendPopupFollowUpEmailJob;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Dashed\DashedPopups\Models\PopupView;
use Illuminate\Support\Carbon;

/**
 * Backfill: voor een nieuwe of gewijzigde popup follow-up flow plant alsnog
 * de emails voor bestaande PopupViews die wel een email hebben ingevuld
 * maar nog niet in een follow-up flow zitten.
 */
class BackfillPopupFollowUpFlowService
{
    /**
     * @return array{
     *     views_started: int,
     *     views_skipped_already_started: int,
     *     views_skipped_cancelled: int,
     *     views_skipped_no_email: int,
     *     emails_dispatched: int,
     * }
     */
    public function run(PopupFollowUpFlow $flow, int $sinceDays = 30, ?int $onlyPopupId = null): array
    {
        $stats = [
            'views_started' => 0,
            'views_skipped_already_started' => 0,
            'views_skipped_cancelled' => 0,
            'views_skipped_no_email' => 0,
            'emails_dispatched' => 0,
        ];

        $emails = $flow->activeEmails()->orderBy('sort')->get();

        if ($emails->isEmpty()) {
            return $stats;
        }

        $since = Carbon::now()->subDays(max(1, $sinceDays))->startOfDay();

        if ($onlyPopupId !== null) {
            $candidatePopupIds = [$onlyPopupId];
        } else {
            $candidatePopupIds = \Dashed\DashedPopups\Models\Popup::query()
                ->where(function ($q) use ($flow) {
                    $q->where('follow_up_flow_id', $flow->id)
                        ->orWhereNull('follow_up_flow_id');
                })
                ->pluck('id')
                ->all();
        }

        if (empty($candidatePopupIds)) {
            return $stats;
        }

        $views = PopupView::query()
            ->whereIn('popup_id', $candidatePopupIds)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $since)
            ->get();

        foreach ($views as $view) {
            if (blank($view->email)) {
                $stats['views_skipped_no_email']++;

                continue;
            }

            if ($view->follow_up_cancelled_at !== null) {
                $stats['views_skipped_cancelled']++;

                continue;
            }

            // Re-resolve flow per view (popup may have its own follow_up_flow_id);
            // skip when the resolved flow isn't this one — we don't want to
            // hijack views that belong to a different flow.
            $popup = $view->popup;
            $resolved = $popup?->resolveFollowUpFlow();
            if (! $resolved || $resolved->id !== $flow->id) {
                continue;
            }

            if ($view->follow_up_started_at !== null) {
                $stats['views_skipped_already_started']++;

                continue;
            }

            $view->update(['follow_up_started_at' => now()]);

            foreach ($emails as $email) {
                SendPopupFollowUpEmailJob::dispatch($view->id, $email->id)
                    ->delay(now()->addMinutes((int) $email->send_after_minutes));
                $stats['emails_dispatched']++;
            }

            $stats['views_started']++;
        }

        return $stats;
    }
}
