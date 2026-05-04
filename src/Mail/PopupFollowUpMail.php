<?php

namespace Dashed\DashedPopups\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedPopups\Models\PopupFollowUpEmail;

/**
 * Renders popup follow-up emails through the unified dashed-core email layout
 * (header with logo/site-name, primary-color band, footer) so they look the
 * same as the rest of the system mails (admin order confirmation, payment
 * link, password reset, etc.). Each saved block is rendered to a <tr><td>
 * row that fits the layout's table structure. Missing block types fall back
 * to the core registry where possible.
 */
class PopupFollowUpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public ?string $previewDiscountCode = null;

    public ?string $previewDiscountValue = null;

    public function __construct(
        public readonly PopupView $popupView,
        public readonly PopupFollowUpEmail $followUpEmail,
        ?string $locale = null,
    ) {
        if ($locale !== null) {
            $this->locale = $locale;
        }
    }

    public function build(): static
    {
        $locale = $this->locale ?? $this->popupView->locale ?? app()->getLocale();

        $siteName = $this->resolveSiteName();
        $discountInfo = $this->resolvePopupDiscountInfo();
        $discountCode = $discountInfo['code'];
        $discountValue = $discountInfo['value'];
        $siteUrl = (string) ($this->customsettingGet('site_url') ?: config('app.url') ?: '');

        $variables = [
            ':siteName:' => $siteName,
            ':email:' => (string) $this->popupView->email,
            ':discountCode:' => $discountCode,
            ':discountValue:' => $discountValue,
            ':siteUrl:' => $siteUrl,
        ];

        $subject = (string) $this->followUpEmail->getTranslation('subject', $locale, false);
        $subject = strtr($subject, $variables);
        if ($subject === '') {
            $subject = $siteName;
        }

        $rawBlocks = $this->followUpEmail->getTranslation('blocks', $locale, false) ?? [];
        if (! is_array($rawBlocks)) {
            $rawBlocks = [];
        }

        $primaryColor = $this->customsettingGet('mail_primary_color') ?: '#A0131C';
        $textColor = $this->customsettingGet('mail_text_color', '#ffffff');
        $backgroundColor = $this->customsettingGet('mail_background_color', '#f3f4f6');
        $footerText = $this->customsettingGet('mail_footer_text');

        $renderedBlocks = [];
        foreach ($rawBlocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $rendered = $this->renderBlock($block, $variables, $discountCode, $discountValue, $primaryColor, $textColor);
            if ($rendered !== null && $rendered !== '') {
                $renderedBlocks[] = $rendered;
            }
        }

        $showLogo = (bool) $this->customsettingGet('mail_show_logo', 1);
        $showSiteName = (bool) $this->customsettingGet('mail_show_site_name', 1);
        $siteLogo = null;
        if ($showLogo && function_exists('mediaHelper')) {
            $logoId = $this->customsettingGet('mail_logo') ?: $this->customsettingGet('site_logo');
            if ($logoId) {
                $media = mediaHelper()->getSingleMedia($logoId);
                $siteLogo = $media->url ?? null;
            }
        }

        $unsubscribeUrl = null;
        if ($this->popupView->id ?? null) {
            try {
                $unsubscribeUrl = \Illuminate\Support\Facades\URL::signedRoute(
                    'dashed.frontend.popup-follow-up.unsubscribe',
                    ['view' => $this->popupView->id],
                );
            } catch (\Throwable $e) {
                $unsubscribeUrl = null;
            }
        }

        $mail = $this
            ->subject($subject)
            ->view('dashed-core::emails.layout')
            ->with([
                'blocks' => $renderedBlocks,
                'siteName' => $siteName,
                'siteLogo' => $siteLogo,
                'siteUrl' => $siteUrl,
                'showSiteName' => $showSiteName,
                'primaryColor' => $primaryColor,
                'textColor' => $textColor,
                'backgroundColor' => $backgroundColor,
                'footerText' => $footerText,
                'unsubscribeUrl' => $unsubscribeUrl,
                'unsubscribeLabel' => 'Afmelden voor deze popup-opvolg-mails',
            ]);

        $fromEmail = $this->resolveFromEmail();
        if ($fromEmail) {
            $mail->from($fromEmail, $siteName);
        }

        return $mail;
    }

    protected function renderBlock(
        array $block,
        array $variables,
        string $discountCode,
        string $discountValue,
        string $primaryColor,
        string $textColor,
    ): ?string {
        $type = $block['type'] ?? null;
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $sub = static fn ($v) => is_string($v) ? strtr($v, $variables) : $v;

        switch ($type) {
            case 'heading':
                $text = $sub($data['text'] ?? $data['content'] ?? '');
                if ($text === '') {
                    return null;
                }

                return view('dashed-core::emails.blocks.heading', [
                    'text' => $text,
                    'level' => $data['level'] ?? 'h2',
                ])->render();

            case 'paragraph':
            case 'text':
                $body = $sub($data['body'] ?? $data['content'] ?? '');
                if ($body === '') {
                    return null;
                }

                return view('dashed-core::emails.blocks.text', ['body' => $body])->render();

            case 'button':
                return view('dashed-core::emails.blocks.button', [
                    'label' => $sub($data['label'] ?? 'Bekijk'),
                    'url' => $sub($data['url'] ?? '#'),
                    'background' => $primaryColor,
                    'color' => $textColor,
                ])->render();

            case 'image':
                $src = $sub($data['src'] ?? $data['url'] ?? '');
                if ($src === '') {
                    return null;
                }

                return view('dashed-core::emails.blocks.image', [
                    'src' => $src,
                    'alt' => $sub($data['alt'] ?? ''),
                    'url' => $sub($data['link'] ?? ''),
                ])->render();

            case 'divider':
                return view('dashed-core::emails.blocks.divider')->render();

            case 'usp':
                $items = collect(explode("\n", (string) ($data['items'] ?? '')))
                    ->map(fn ($i) => trim((string) $i))
                    ->filter()
                    ->map($sub)
                    ->all();
                if (! $items) {
                    return null;
                }
                $list = '';
                foreach ($items as $item) {
                    $list .= '<li>'.htmlspecialchars($item, ENT_QUOTES, 'UTF-8').'</li>';
                }

                return '<tr><td style="padding: 8px 24px;"><ul style="margin:0; padding-left:20px; font-family: Arial, sans-serif; font-size:14px; line-height:1.8; color:#374151;">'.$list.'</ul></td></tr>';

            case 'discount':
                $label = $sub($data['label'] ?? 'Gebruik deze code voor extra korting:');
                $code = trim((string) $sub($data['code'] ?? '')) ?: $discountCode;
                if ($code === '') {
                    return null;
                }

                $valueRow = '';
                if ($discountValue !== '') {
                    $valueRow = '<div style="font-family: Arial, sans-serif; font-size:14px; color:#374151; margin-top:10px;">Bespaar <strong>'
                        .htmlspecialchars($discountValue, ENT_QUOTES, 'UTF-8')
                        .'</strong> op je bestelling</div>';
                }

                return '<tr><td align="center" style="padding: 16px 24px;">'
                    .'<div style="font-family: Arial, sans-serif; font-size:14px; color:#374151; margin-bottom:8px;">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</div>'
                    .'<div style="display:inline-block; padding:12px 24px; background:'.$primaryColor.'; color:'.$textColor.'; font-family: Arial, sans-serif; font-size:18px; font-weight:bold; letter-spacing:1px; border-radius:6px;">'.htmlspecialchars($code, ENT_QUOTES, 'UTF-8').'</div>'
                    .$valueRow
                    .'</td></tr>';
        }

        $registry = function_exists('cms') ? cms()->emailBlocks() : [];
        if ($type && isset($registry[$type])) {
            $class = $registry[$type];

            return $class::render($data, [
                'siteName' => $variables[':siteName:'] ?? '',
                'primaryColor' => $primaryColor,
                'textColor' => $textColor,
            ]);
        }

        return null;
    }

    protected function customsettingGet(string $key, mixed $default = null): mixed
    {
        if (class_exists(\Dashed\DashedCore\Models\Customsetting::class)
            && class_exists(\Dashed\DashedCore\Classes\Sites::class)) {
            return \Dashed\DashedCore\Models\Customsetting::get(
                $key,
                \Dashed\DashedCore\Classes\Sites::getActive(),
                $default,
            );
        }

        return $default;
    }

    protected function resolveSiteName(): string
    {
        return (string) ($this->customsettingGet('site_name') ?: config('app.name', 'Site'));
    }

    /**
     * @return array{code: string, value: string}
     */
    protected function resolvePopupDiscountInfo(): array
    {
        if ($this->previewDiscountCode !== null) {
            return [
                'code' => (string) $this->previewDiscountCode,
                'value' => (string) ($this->previewDiscountValue ?? ''),
            ];
        }

        $discountCodeId = $this->popupView->discount_code_id ?? null;
        if (! $discountCodeId || ! class_exists(\Dashed\DashedEcommerceCore\Models\DiscountCode::class)) {
            return ['code' => '', 'value' => ''];
        }

        $row = \Dashed\DashedEcommerceCore\Models\DiscountCode::query()
            ->whereKey($discountCodeId)
            ->first(['code', 'type', 'discount_percentage', 'discount_amount']);

        if (! $row) {
            return ['code' => '', 'value' => ''];
        }

        $value = '';
        if ($row->type === 'percentage' && $row->discount_percentage) {
            $pct = (float) $row->discount_percentage;
            $formatted = rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',');
            $value = $formatted.'%';
        } elseif ($row->type === 'amount' && $row->discount_amount) {
            if (class_exists(\Dashed\DashedEcommerceCore\Classes\CurrencyHelper::class)) {
                $value = \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice((float) $row->discount_amount);
            } else {
                $value = '€ '.number_format((float) $row->discount_amount, 2, ',', '.');
            }
        }

        return ['code' => (string) ($row->code ?: ''), 'value' => $value];
    }

    protected function resolveFromEmail(): ?string
    {
        $email = $this->customsettingGet('site_from_email');

        return $email ?: null;
    }
}
