<?php

namespace Dashed\DashedPopups\CustomerProfile;

use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedPopups\Filament\Resources\PopupResource;

/**
 * Popup-inschrijvingen van een klant voor het klantprofiel in ec-core
 * (cms()->builder('customerProfileSections')). Alleen ingevulde popups,
 * geen losse vertoningen.
 */
class PopupCustomerSection
{
    public static function label(): string
    {
        return __('Popup-inschrijvingen');
    }

    public static function sort(): int
    {
        return 70;
    }

    public static function forCustomer(?int $userId, ?string $email): ?array
    {
        $views = PopupView::query()
            ->whereNotNull('submitted_at')
            ->where(fn ($q) => $q->whereRaw('1 = 0')
                ->when($userId, fn ($q) => $q->orWhere('user_id', $userId))
                ->when($email, fn ($q) => $q->orWhere('email', $email)))
            ->with(['popup', 'discountCode'])
            ->latest('submitted_at')
            ->limit(25)
            ->get();

        if ($views->isEmpty()) {
            return null;
        }

        return [
            'columns' => [
                'popup' => __('Popup'),
                'date' => __('Ingevuld op'),
                'code' => __('Kortingscode'),
                'order' => __('Gebruikt'),
            ],
            'rows' => $views->map(fn (PopupView $view) => [
                'popup' => (string) ($view->popup?->name ?? '-'),
                'date' => $view->submitted_at?->format('d-m-Y'),
                'code' => (string) ($view->discountCode?->code ?? '-'),
                'order' => $view->matched_order_id ? __('Ja') : __('Nee'),
                '_url' => $view->popup_id ? rescue(fn () => PopupResource::getUrl('edit', ['record' => $view->popup_id]), null, false) : null,
            ])->all(),
            'empty' => __('Geen popup-inschrijvingen.'),
        ];
    }
}
