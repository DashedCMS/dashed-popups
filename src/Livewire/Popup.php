<?php

namespace Dashed\DashedPopups\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Auth;
use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedEcommerceCore\Models\Cart;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedPopups\Analytics\DeviceDetector;
use Dashed\DashedPopups\Mail\PopupConversionMail;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedPopups\Models\Popup as PopupModel;
use Dashed\DashedEcommerceCore\Jobs\AbandonedCart\ScheduleAbandonedCartEmailsForCartJob;

class Popup extends Component
{
    public ?PopupModel $popup = null;

    public ?PopupView $popupView = null;

    public bool $showPopup = false;

    public string $eventName = 'redirectTo';

    public string $email = '';

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

        $user = Auth::user();
        $identityEmail = $this->resolveIdentityEmail($user);

        // Discount popups would give a customer with custom/contract pricing an extra discount on top.
        if ($this->popup->isDiscountType() && $user && ($user->has_custom_pricing ?? false)) {
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

        $existingCartWithCode = Cart::where('abandoned_email', $email)
            ->whereNotNull('discount_code_id')
            ->whereHas('discountCode', fn ($q) => $q->where('code', 'like', 'WELKOM-%'))
            ->with('discountCode')
            ->latest()
            ->first();

        $existingCode = $existingCartWithCode?->discountCode;

        if ($existingCode) {
            $code = $existingCode;
        } else {
            $code = DiscountCode::create([
                'site_ids' => [Sites::getActive()],
                'name' => 'Popup '.$this->popup->discount_percentage.'% korting',
                'code' => 'WELKOM-'.strtoupper(Str::random(8)),
                'type' => 'percentage',
                'discount_percentage' => $this->popup->discount_percentage,
                'use_stock' => true,
                'stock' => 1,
                'limit_use_per_customer' => true,
                'minimal_requirements' => false,
                'start_date' => now(),
                'end_date' => now()->addDays((int) ($this->popup->discount_valid_days ?? 14)),
            ]);
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
    }

    public function clickAway(): void
    {
        if ($this->popupView) {
            $this->popupView->closed_at = now();
            $this->popupView->save();
        }
        $this->showPopup = false;
    }

    public function render()
    {
        if ($this->popup && view()->exists('dashed.popups.'.str($this->popup->name ?? '')->slug().'-popup')) {
            return view(env('SITE_THEME', 'dashed').'.popups.'.str($this->popup->name)->slug().'-popup');
        }

        return view(env('SITE_THEME', 'dashed').'.popups.popup');
    }
}
