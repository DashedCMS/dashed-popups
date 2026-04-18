<?php

namespace Dashed\DashedPopups\Mail;

use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Models\PopupView;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PopupConversionMail extends Mailable implements SendsToTelegram
{
    use Queueable;
    use SerializesModels;

    public Popup $popup;

    public PopupView $popupView;

    public function __construct(Popup $popup, PopupView $popupView)
    {
        $this->popup = $popup;
        $this->popupView = $popupView;
    }

    public function build()
    {
        // Telegram-only mailable; no view/subject needed.
        return $this;
    }

    public function telegramSummary(): TelegramSummary
    {
        $name = $this->popup->name;
        $name = is_array($name) ? ($name[app()->getLocale()] ?? reset($name)) : $name;
        $name = (string) ($name ?: 'Popup');

        $email = $this->popupView->content['email'] ?? null;
        $code = rescue(fn () => $this->popupView->discountCode?->code, null, false);

        $fields = [
            'Popup' => $name,
            'Email' => $email,
            'Kortingscode' => $code,
            'Tijdstip' => $this->popupView->submitted_at?->format('d-m-Y H:i') ?? '-',
        ];

        return new TelegramSummary(
            title: $name,
            fields: $fields,
            adminUrl: rescue(fn () => route('filament.dashed.resources.popups.edit', ['record' => $this->popup->id]), null, false),
            emoji: '🎯',
        );
    }
}
