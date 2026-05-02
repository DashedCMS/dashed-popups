<?php

namespace Dashed\DashedPopups\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedPopups\Models\PopupFollowUpEmail;

/**
 * Mailable used by SendPopupFollowUpEmailJob.
 *
 * The popup-blocks renderer is the same one used for the popup body itself
 * (see resources/views/blocks/*). We render the email by reusing that block
 * library: each block in the follow-up email is wrapped in a thin email
 * shell view that the host theme can override at
 * <theme>.emails.popup-follow-up. The package ships a sensible default
 * fallback at dashed-popups::emails.follow-up that prints the blocks in
 * order with no decoration.
 */
class PopupFollowUpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PopupView $popupView,
        public readonly PopupFollowUpEmail $followUpEmail,
        public readonly ?string $locale = null,
    ) {
    }

    public function build(): static
    {
        $locale = $this->locale ?? $this->popupView->locale ?? app()->getLocale();

        $siteName = $this->resolveSiteName();
        $variables = [
            ':siteName:' => $siteName,
            ':email:' => (string) $this->popupView->email,
        ];

        $subject = (string) $this->followUpEmail->getTranslation('subject', $locale, false);
        $subject = str_replace(array_keys($variables), array_values($variables), $subject);
        if ($subject === '') {
            $subject = $siteName;
        }

        $blocks = $this->followUpEmail->getTranslation('blocks', $locale, false) ?? [];
        if (! is_array($blocks)) {
            $blocks = [];
        }
        $blocks = collect($blocks)->map(function ($block) use ($variables) {
            if (! is_array($block)) {
                return $block;
            }
            if (($block['type'] ?? null) === 'text' && ! empty($block['data']['content'] ?? null)) {
                $block['data']['content'] = str_replace(
                    array_keys($variables),
                    array_values($variables),
                    (string) $block['data']['content'],
                );
            }

            return $block;
        })->all();

        $themedView = config('dashed-core.site_theme', 'dashed').'.emails.popup-follow-up';
        $view = view()->exists($themedView)
            ? $themedView
            : 'dashed-popups::emails.follow-up';

        $mail = $this
            ->subject($subject)
            ->view($view)
            ->with([
                'popupView' => $this->popupView,
                'followUpEmail' => $this->followUpEmail,
                'blocks' => $blocks,
                'siteName' => $siteName,
                'popupDiscountCode' => $this->resolvePopupDiscountCode(),
            ]);

        $fromEmail = $this->resolveFromEmail();
        if ($fromEmail) {
            $mail->from($fromEmail, $siteName);
        }

        return $mail;
    }

    protected function resolveSiteName(): string
    {
        if (class_exists(\Dashed\DashedCore\Models\Customsetting::class)
            && class_exists(\Dashed\DashedCore\Classes\Sites::class)) {
            return (string) \Dashed\DashedCore\Models\Customsetting::get(
                'site_name',
                \Dashed\DashedCore\Classes\Sites::getActive(),
                config('app.name'),
            );
        }

        return (string) config('app.name', 'Site');
    }

    protected function resolvePopupDiscountCode(): ?string
    {
        $discountCodeId = $this->popupView->discount_code_id ?? null;
        if (! $discountCodeId) {
            return null;
        }

        if (class_exists(\Dashed\DashedEcommerceCore\Models\DiscountCode::class)) {
            $code = \Dashed\DashedEcommerceCore\Models\DiscountCode::query()
                ->whereKey($discountCodeId)
                ->value('code');

            return $code ?: null;
        }

        return null;
    }

    protected function resolveFromEmail(): ?string
    {
        if (class_exists(\Dashed\DashedCore\Models\Customsetting::class)
            && class_exists(\Dashed\DashedCore\Classes\Sites::class)) {
            $email = \Dashed\DashedCore\Models\Customsetting::get(
                'site_from_email',
                \Dashed\DashedCore\Classes\Sites::getActive(),
            );

            return $email ?: null;
        }

        return null;
    }
}
