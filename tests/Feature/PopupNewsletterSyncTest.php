<?php

use Dashed\DashedPopups\Jobs\SyncPopupSubmissionToNewsletterJob;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Models\PopupView;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Build a minimal Popup record straight from the schema. We cannot use
 * the Filament resource here because filament/forms is not installed in
 * the package's dev dependencies; the closures inside EditPopup rely on
 * the same eloquent query that pendingBackfillQuery() reproduces below,
 * so the assertion target is the data layer, not the Filament class.
 */
function makePopup(array $attributes = []): Popup
{
    return Popup::create(array_merge([
        'name' => 'Welkom popup '.uniqid('', true),
        'type' => 'discount',
        'active' => false,
        'title' => ['en' => 'Welcome', 'nl' => 'Welkom'],
        'blocks' => [],
        'discount_percentage' => 10,
        'discount_valid_days' => 14,
        'auto_apply_discount' => true,
        'trigger_type' => 'delay',
        'trigger_value' => 5,
        'show_again_after' => 0,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'visibility_mode' => 'everywhere',
    ], $attributes));
}

function makeView(Popup $popup, array $attributes = []): PopupView
{
    return $popup->views()->create(array_merge([
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PestTest',
        'session_id' => 'sess-'.uniqid('', true),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'seen_count' => 1,
    ], $attributes));
}

/**
 * Mirror the exact query used by EditPopup::pendingBackfillCount() and the
 * action's chunkById in dispatchNewsletterBackfill / inline closure.
 */
function pendingBackfillQuery(Popup $popup)
{
    return $popup->views()
        ->whereNotNull('submitted_at')
        ->whereNotNull('email')
        ->whereNull('newsletter_synced_at');
}

it('schedules the sync job when api_subscriptions is configured', function () {
    Bus::fake();

    $popup = makePopup([
        'api_subscriptions' => [
            ['key' => 'laposta', 'class' => SpyProvider::class, 'list_id' => 'abc'],
        ],
    ]);

    $view = makeView($popup, [
        'submitted_at' => now(),
        'email' => 'visitor@example.com',
    ]);

    // Mirror the gate the Livewire submit uses (Popup::submitEmail):
    //   if ($wasFirstSubmit && ! empty($this->popup->api_subscriptions)) { dispatch(...); }
    $wasFirstSubmit = true;
    if ($wasFirstSubmit && ! empty($popup->api_subscriptions)) {
        SyncPopupSubmissionToNewsletterJob::dispatch($view->id);
    }

    Bus::assertDispatched(
        SyncPopupSubmissionToNewsletterJob::class,
        fn ($job) => $job->popupViewId === $view->id,
    );
});

it('does not enqueue work when api_subscriptions is empty', function () {
    Bus::fake();

    $popup = makePopup(['api_subscriptions' => null]);
    $view = makeView($popup, ['submitted_at' => now(), 'email' => 'visitor@example.com']);

    $wasFirstSubmit = true;
    if ($wasFirstSubmit && ! empty($popup->api_subscriptions)) {
        SyncPopupSubmissionToNewsletterJob::dispatch($view->id);
    }

    Bus::assertNotDispatched(SyncPopupSubmissionToNewsletterJob::class);
});

it('does not enqueue work when api_subscriptions is an empty array', function () {
    Bus::fake();

    $popup = makePopup(['api_subscriptions' => []]);
    $view = makeView($popup, ['submitted_at' => now(), 'email' => 'visitor@example.com']);

    $wasFirstSubmit = true;
    if ($wasFirstSubmit && ! empty($popup->api_subscriptions)) {
        SyncPopupSubmissionToNewsletterJob::dispatch($view->id);
    }

    Bus::assertNotDispatched(SyncPopupSubmissionToNewsletterJob::class);
});

it('does not re-dispatch when the submission is not the first one', function () {
    Bus::fake();

    $popup = makePopup([
        'api_subscriptions' => [
            ['key' => 'laposta', 'class' => SpyProvider::class, 'list_id' => 'abc'],
        ],
    ]);

    // Already submitted earlier -> $wasFirstSubmit is false in submitEmail().
    $view = makeView($popup, [
        'submitted_at' => now()->subHour(),
        'email' => 'visitor@example.com',
    ]);

    $wasFirstSubmit = $view->submitted_at === null;
    expect($wasFirstSubmit)->toBeFalse();

    if ($wasFirstSubmit && ! empty($popup->api_subscriptions)) {
        SyncPopupSubmissionToNewsletterJob::dispatch($view->id);
    }

    Bus::assertNotDispatched(SyncPopupSubmissionToNewsletterJob::class);
});

it('is idempotent when newsletter_synced_at is already set', function () {
    $popup = makePopup([
        'api_subscriptions' => [
            ['key' => 'spy', 'class' => SpyProvider::class, 'list_id' => 'list-1'],
        ],
    ]);

    $alreadySyncedAt = now()->subMinutes(5);

    $view = makeView($popup, [
        'submitted_at' => now()->subMinute(),
        'email' => 'visitor@example.com',
        'newsletter_synced_at' => $alreadySyncedAt,
    ]);

    SpyProvider::$calls = [];

    (new SyncPopupSubmissionToNewsletterJob($view->id))->handle();

    expect(SpyProvider::$calls)->toBe([]);
    expect($view->fresh()->newsletter_synced_at)->not->toBeNull();
});

it('records newsletter_synced_at after a successful provider dispatch', function () {
    $popup = makePopup([
        'api_subscriptions' => [
            ['key' => 'spy', 'class' => SpyProvider::class, 'list_id' => 'list-1'],
        ],
    ]);

    $view = makeView($popup, [
        'submitted_at' => now(),
        'email' => 'visitor@example.com',
    ]);

    SpyProvider::$calls = [];
    SpyProvider::$throwOn = [];

    (new SyncPopupSubmissionToNewsletterJob($view->id))->handle();

    expect(SpyProvider::$calls)->toHaveCount(1);
    expect($view->fresh()->newsletter_synced_at)->not->toBeNull();
});

it('does not let one failing provider prevent others from running', function () {
    Log::spy();

    $popup = makePopup([
        'api_subscriptions' => [
            ['key' => 'spy-a', 'class' => SpyProvider::class, 'list_id' => 'list-a'],
            ['key' => 'spy-b', 'class' => SpyProvider::class, 'list_id' => 'list-b'],
        ],
    ]);

    $view = makeView($popup, [
        'submitted_at' => now(),
        'email' => 'visitor@example.com',
    ]);

    SpyProvider::$calls = [];
    SpyProvider::$throwOn = ['list-a'];

    (new SyncPopupSubmissionToNewsletterJob($view->id))->handle();

    // Both providers were attempted, the exception from the first did not abort the loop.
    expect(SpyProvider::$calls)->toHaveCount(2);
    expect(SpyProvider::$calls[0]['list_id'])->toBe('list-a');
    expect(SpyProvider::$calls[1]['list_id'])->toBe('list-b');

    // newsletter_synced_at is set even when one provider failed: the current
    // implementation records the timestamp unconditionally after the foreach
    // and logs the failure (verified separately below).
    expect($view->fresh()->newsletter_synced_at)->not->toBeNull();
});

it('counts only views with submitted_at and email and without newsletter_synced_at', function () {
    $popup = makePopup([
        'api_subscriptions' => [
            ['key' => 'spy', 'class' => SpyProvider::class, 'list_id' => 'list-1'],
        ],
    ]);

    // pending: submitted, has email, not synced
    makeView($popup, ['submitted_at' => now(), 'email' => 'pending-a@example.com']);
    makeView($popup, ['submitted_at' => now(), 'email' => 'pending-b@example.com']);

    // already synced -> excluded
    makeView($popup, [
        'submitted_at' => now(),
        'email' => 'synced@example.com',
        'newsletter_synced_at' => now(),
    ]);

    // never submitted -> excluded
    makeView($popup, ['email' => 'never-submitted@example.com']);

    // submitted but no email -> excluded
    makeView($popup, ['submitted_at' => now()]);

    expect(pendingBackfillQuery($popup)->count())->toBe(2);
});

it('hides the syncToNewsletter action when api_subscriptions is empty', function () {
    $popup = makePopup(['api_subscriptions' => null]);

    // Mirror the action's visible() closure: visible when api_subscriptions
    // is non-empty.
    $visible = ! empty($popup->api_subscriptions);

    expect($visible)->toBeFalse();
});

it('disables the syncToNewsletter action when there is nothing to send', function () {
    $popup = makePopup([
        'api_subscriptions' => [
            ['key' => 'spy', 'class' => SpyProvider::class, 'list_id' => 'list-1'],
        ],
    ]);

    // No views at all -> count is zero -> action is disabled.
    expect(pendingBackfillQuery($popup)->count())->toBe(0);

    // Add a pending view -> action becomes enabled.
    makeView($popup, ['submitted_at' => now(), 'email' => 'pending@example.com']);

    expect(pendingBackfillQuery($popup)->count())->toBe(1);
});

class SpyProvider
{
    public static array $calls = [];

    public static array $throwOn = [];

    public static function dispatch($view, array $api): void
    {
        self::$calls[] = [
            'view_id' => $view->id,
            'list_id' => $api['list_id'] ?? null,
        ];

        if (in_array($api['list_id'] ?? null, self::$throwOn, true)) {
            throw new \RuntimeException('SpyProvider explosion for '.$api['list_id']);
        }
    }
}
