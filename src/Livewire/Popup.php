<?php

namespace Dashed\DashedPopups\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Auth;
use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedPopups\Models\PopupVariant;
use Dashed\DashedPopups\Analytics\DeviceDetector;
use Dashed\DashedPopups\Mail\PopupConversionMail;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedPopups\Models\Popup as PopupModel;
use Dashed\DashedPopups\Jobs\SendPopupFollowUpEmailJob;
use Dashed\DashedPopups\Jobs\SyncPopupSubmissionToNewsletterJob;
use Dashed\DashedEcommerceCore\Jobs\AbandonedCart\ScheduleAbandonedCartEmailsForCartJob;

class Popup extends Component
{
    public ?PopupModel $popup = null;

    public ?PopupView $popupView = null;

    public bool $showPopup = false;

    public string $eventName = 'redirectTo';

    public $email = '';

    public bool $showSuccess = false;

    public ?string $discountCode = null;

    protected function rules(): array
    {
        return [
            'email' => 'required|email:rfc',
        ];
    }

    public function mount(string|int $popupId, ?string $eventName = null): void
    {
        if ($eventName) {
            $this->eventName = $eventName;
        }

        $this->popup = PopupModel::where('name', $popupId)
            ->orWhere('id', $popupId)
            ->first();

        if (! $this->popup) {
            return;
        }

        if (! $this->popup->shouldShowFor(request())) {
            return;
        }

        // Globale tussentijd tussen popups. Meerdere popups mogen tegelijk
        // actief zijn, maar een bezoeker krijgt er niet in korte tijd twee
        // achter elkaar: er verschijnt hooguit één popup per pagina-load en
        // pas weer een volgende nadat de ingestelde tussentijd is verstreken.
        $gapMinutes = (int) Customsetting::get('popups_minutes_between', Sites::getActive(), 30);
        $lastShownAt = session('dashed_popups.last_shown_at');
        if (PopupModel::globalGapBlocks($lastShownAt ? (int) $lastShownAt : null, $gapMinutes, now()->timestamp)) {
            return;
        }

        $user = Auth::user();
        $identityEmail = $this->resolveIdentityEmail($user);

        // Discount popups would give a customer with custom/contract pricing an extra discount on top.
        if ($this->popup->isDiscountType() && $user && ($user->has_custom_pricing ?? false)) {
            return;
        }

        // Discount popups: skip wanneer deze gebruiker al een korting heeft geclaimd via een
        // andere discount-popup. Voorkomt dat ze bij elke nieuwe popup opnieuw moeten invullen.
        if ($this->popup->isDiscountType() && $this->hasAlreadyClaimedDiscount($user?->id, $identityEmail)) {
            return;
        }

        // Skip if this user or email has already seen this popup in any prior session.
        if ($this->alreadySeenByIdentity($user?->id, $identityEmail)) {
            return;
        }

        $popupView = $this->popup->views()
            ->where('session_id', session()->getId())
            ->first();

        $inWindow = $this->popup->start_date <= now() && $this->popup->end_date >= now();

        if (! $popupView) {
            $popupView = $this->popup->views()->create([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent() ?? '',
                'session_id' => session()->getId(),
                'user_id' => $user?->id,
                'email' => $identityEmail,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'seen_count' => 0,
                'device_type' => app(DeviceDetector::class)->detect(request()->userAgent()),
                'url' => substr((string) request()->headers->get('referer', ''), 0, 500) ?: null,
                'referrer' => substr((string) request()->headers->get('x-dashed-referrer', ''), 0, 500) ?: null,
                'locale' => app()->getLocale(),
                'triggered_by' => $this->popup->trigger_type ?? 'delay',
            ]);
            $this->showPopup = $inWindow;

            if ($popupView->variant_id === null) {
                $variant = PopupVariant::pickForPopup($this->popup->id);
                if ($variant) {
                    $popupView->variant_id = $variant->id;
                    $popupView->save();
                }
            }
        } else {
            $cooldownPassed = ! $popupView->closed_at
                || $popupView->closed_at < now()->subMinutes((int) ($this->popup->show_again_after ?? 0));
            $this->showPopup = $inWindow && $cooldownPassed;

            $identityDirty = false;
            if ($user && ! $popupView->user_id) {
                $popupView->user_id = $user->id;
                $identityDirty = true;
            }
            if ($identityEmail && ! $popupView->email) {
                $popupView->email = $identityEmail;
                $identityDirty = true;
            }
            if ($identityDirty) {
                $popupView->save();
            }
        }

        $this->popupView = $popupView;

        if ($this->showPopup) {
            $this->popupView->seen_count++;
            $this->popupView->last_seen_at = now();
            $this->popupView->save();

            session(['dashed_popups.last_shown_at' => now()->timestamp]);
        }
    }

    protected function resolveIdentityEmail($user): ?string
    {
        $email = $user?->email ?? session('popup_identity_email');

        return $email ? strtolower(trim($email)) : null;
    }

    protected function alreadySeenByIdentity(?int $userId, ?string $email): bool
    {
        if (! $userId && ! $email) {
            return false;
        }

        return $this->popup->views()
            ->where('session_id', '!=', session()->getId())
            ->where(function ($q) use ($userId, $email) {
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->exists();
    }

    /**
     * True wanneer de gebruiker al ergens (welke discount-popup dan ook) een
     * email heeft ingevuld + een kortingscode heeft ontvangen. Voorkomt dat
     * dezelfde gebruiker bij elke volgende discount-popup opnieuw zijn email
     * moet invullen.
     */
    protected function hasAlreadyClaimedDiscount(?int $userId, ?string $email): bool
    {
        if (! $userId && ! $email) {
            return false;
        }

        return PopupView::query()
            ->whereNotNull('submitted_at')
            ->whereNotNull('discount_code_id')
            ->where(function ($q) use ($userId, $email) {
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->exists();
    }

    public function submitEmail(): void
    {
        if (! $this->popup || ! $this->popup->isDiscountType()) {
            return;
        }

        $validated = $this->validate();
        $email = $validated['email'];

        $rateKey = 'popup-submit:'.request()->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $this->addError('email', __('Teveel pogingen. Probeer het later opnieuw.'));

            return;
        }
        RateLimiter::hit($rateKey, 3600);

        $cart = cartHelper()->getCart();

        $variant = $this->popupView?->variant;
        $codePrefix = $variant?->code_prefix
            ? 'WELKOM-'.strtoupper($variant->code_prefix).'-'
            : 'WELKOM-';
        $discountPercentage = $variant?->resolvedDiscountPercentage() ?? (float) $this->popup->discount_percentage;
        $validDays = $variant?->resolvedValidDays() ?? (int) $this->popup->discount_valid_days;

        $existingCartWithCode = Cart::where('abandoned_email', $email)
            ->whereNotNull('discount_code_id')
            ->whereHas('discountCode', fn ($q) => $q->where('code', 'like', $codePrefix.'%'))
            ->with('discountCode')
            ->latest()
            ->first();

        $existingCode = $existingCartWithCode?->discountCode;

        if ($existingCode) {
            $code = $existingCode;
        } else {
            $code = $this->popup->createDiscountCodeFor(
                $codePrefix.strtoupper(Str::random(8)),
                (float) $discountPercentage,
                (int) $validDays,
                [Sites::getActive()],
            );
        }

        $updates = ['abandoned_email' => $email];
        if ($this->popup->auto_apply_discount) {
            $updates['discount_code_id'] = $code->id;
        }
        $cart->update($updates);

        $wasFirstSubmit = $this->popupView && $this->popupView->submitted_at === null;

        if ($this->popupView) {
            $this->popupView->update([
                'submitted_at' => now(),
                'discount_code_id' => $code->id,
                'email' => strtolower(trim($email)),
                'content' => ['email' => $email],
            ]);
        }

        session(['popup_identity_email' => strtolower(trim($email))]);

        // Globale captura zodat alle blades / formulieren in dezelfde
        // sessie het adres kunnen voorvullen of personaliseren.
        \Dashed\DashedCore\Classes\EmailCapture::capture($email, 'popup');

        dispatch(new ScheduleAbandonedCartEmailsForCartJob($cart->id));

        if ($this->popup->notify_on_conversion && $wasFirstSubmit) {
            try {
                AdminNotifier::send(
                    new PopupConversionMail($this->popup, $this->popupView),
                    Mails::getAdminNotificationEmails(),
                    ['telegram'],
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($wasFirstSubmit && ! empty($this->popup->api_subscriptions)) {
            SyncPopupSubmissionToNewsletterJob::dispatch($this->popupView->id);
        }

        if ($wasFirstSubmit && $this->popupView) {
            $flow = $this->popup->resolveFollowUpFlow();
            if ($flow && ! $this->popupView->follow_up_started_at) {
                $this->popupView->update(['follow_up_started_at' => now()]);
                foreach ($flow->emails()->where('is_active', true)->get() as $followUpEmail) {
                    SendPopupFollowUpEmailJob::dispatch($this->popupView->id, $followUpEmail->id)
                        ->delay(now()->addMinutes((int) $followUpEmail->send_after_minutes));
                }
            }
        }

        $this->discountCode = $code->code;
        $this->showSuccess = true;
    }

    public function goTo(): void
    {
        if ($this->popupView) {
            $this->popupView->closed_at = now();
            $this->popupView->save();
        }
        $this->showPopup = false;
        $this->dispatch($this->eventName);

        if ($this->showSuccess) {
            $this->dispatch('closeAllPopups');
        }
    }

    public function clickAway(): void
    {
        if ($this->popupView) {
            $this->popupView->closed_at = now();
            $this->popupView->save();
        }
        $this->showPopup = false;

        if ($this->showSuccess) {
            $this->dispatch('closeAllPopups');
        }
    }

    public function render()
    {
        if ($this->popup && view()->exists('dashed.popups.'.str($this->popup->name ?? '')->slug().'-popup')) {
            return view(env('SITE_THEME', 'dashed').'.popups.'.str($this->popup->name)->slug().'-popup');
        }

        return view(env('SITE_THEME', 'dashed').'.popups.popup');
    }
}
