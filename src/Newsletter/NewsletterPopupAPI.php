<?php

declare(strict_types=1);

namespace Dashed\DashedPopups\Newsletter;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedNewsletter\Models\NewsletterList;

/**
 * Zet een adres dat via een popup binnenkomt op een CMS-nieuwsbrieflijst.
 *
 * Staat in dit pakket en niet in dashed-newsletter, want hier woont PopupView.
 * De nieuwsbriefmodule hoort niets van popups te weten. Registratie gebeurt
 * achter een guard, zodat een site zonder nieuwsbriefmodule hier geen last van
 * heeft.
 */
class NewsletterPopupAPI
{
    /**
     * @param array<string, mixed> $api
     */
    public static function dispatch(PopupView $view, array $api): void
    {
        if (! app()->bound('newsletter')) {
            return;
        }

        $list = NewsletterList::find($api['newsletter_list_id'] ?? null);

        if (! $list) {
            throw new \RuntimeException('Nieuwsbrief: de ingestelde lijst bestaat niet meer.');
        }

        if (! $view->email) {
            return;
        }

        $fields = [];

        foreach ($api['customFields'] ?? [] as $customField) {
            $value = self::resolveFieldValue($view, $customField['field_id'] ?? null);

            if (filled($value) && filled($customField['newsletter_field_key'] ?? null)) {
                $fields[$customField['newsletter_field_key']] = $value;
            }
        }

        // Bewust geen eigen foutafhandeling eromheen: SyncPopupSubmissionToNewsletterJob
        // vangt en logt wat hier misgaat, en een fout stil opeten zou een
        // aanmelding laten verdwijnen zonder spoor.
        app('newsletter')->subscribe(
            email: $view->email,
            list: $list,
            fields: $fields,
            source: 'popup',
            consentText: $api['consent_text'] ?? null,
            ip: $view->ip_address,
        );
    }

    public static function formFields(): array
    {
        return [
            Select::make('newsletter_list_id')
                ->label(__('Nieuwsbrieflijst'))
                ->required()
                ->options(fn (): array => NewsletterList::pluck('name', 'id')->all()),
            Repeater::make('customFields')
                ->label(__('Gekoppelde velden'))
                ->schema([
                    Select::make('field_id')
                        ->label(__('Veld uit popup'))
                        ->options([
                            'email' => __('Email'),
                            'url' => __('Pagina URL'),
                            'referrer' => __('Referrer'),
                            'device_type' => __('Apparaat type'),
                            'locale' => __('Taal'),
                        ]),
                    TextInput::make('newsletter_field_key')
                        ->label(__('Sleutel van het nieuwsbriefveld'))
                        ->required(),
                ])
                ->columnSpanFull(),
            Textarea::make('consent_text')
                ->label(__('Toestemmingstekst'))
                ->helperText(__('De tekst die in de popup naast het aanmeldveld stond. Deze wordt letterlijk bewaard als bewijs.'))
                ->rows(2)
                ->required(),
        ];
    }

    protected static function resolveFieldValue(PopupView $view, ?string $fieldId): ?string
    {
        return match ($fieldId) {
            'email' => $view->email,
            'url' => $view->url,
            'referrer' => $view->referrer,
            'device_type' => $view->device_type,
            'locale' => $view->locale,
            default => null,
        };
    }
}
