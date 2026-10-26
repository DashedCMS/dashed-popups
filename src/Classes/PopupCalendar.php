<?php

namespace Dashed\DashedPopups\Classes;

use Illuminate\Support\Carbon;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Models\PopupTarget;
use Dashed\DashedCore\Classes\Trash\Trash;
use Dashed\DashedCore\Classes\Calendar\CalendarEvent;
use Dashed\DashedCore\Classes\Calendar\CampaignCalendar;

/**
 * Actieve popups op de campagnekalender. Twee popups die tegelijk op
 * dezelfde pagina kunnen verschijnen zijn een conflict: een popup zonder
 * insluitregels staat overal (`popup:*`), "alle pagina's van een soort"
 * botst met elke losse pagina van die soort.
 */
class PopupCalendar
{
    public static function register(): void
    {
        if (! class_exists(CampaignCalendar::class)) {
            return;
        }

        CampaignCalendar::source('popups', __('Popups'), '#db2777', function (Carbon $from, Carbon $to) {
            // Popups kennen geen site: ze staan op elke site van de kalender.
            return Popup::query()
                ->where('active', true)
                ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $to))
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
                ->with('includeTargets')
                ->limit(200)
                ->get()
                ->map(fn (Popup $popup) => new CalendarEvent(
                    source: 'popups',
                    title: __('Popup: :naam', ['naam' => $popup->name]),
                    start: $popup->start_date ? Carbon::parse($popup->start_date) : $from->copy(),
                    end: $popup->end_date ? Carbon::parse($popup->end_date) : null,
                    url: Trash::editUrl($popup),
                    overlapKeys: self::overlapKeys($popup),
                ))
                ->values()
                ->all();
        });
    }

    /**
     * @return array<int, string>
     */
    public static function overlapKeys(Popup $popup): array
    {
        // Zonder "alleen op de selectie" staat een popup overal (op de
        // uitsluitingen na), net als in PopupTargetingService.
        if ($popup->visibility_mode !== 'only_selection' || $popup->includeTargets->isEmpty()) {
            return ['popup:*'];
        }

        return $popup->includeTargets->map(fn (PopupTarget $target) => match ($target->match_type) {
            PopupTarget::MATCH_ALL_OF_TYPE => 'popup:model:'.$target->targetable_type.':*',
            PopupTarget::MATCH_SPECIFIC_MODEL => 'popup:model:'.$target->targetable_type.':'.$target->targetable_id,
            PopupTarget::MATCH_URL_PATTERN => 'popup:url:'.$target->pattern,
            default => 'popup:other:'.$target->id,
        })->unique()->values()->all();
    }
}
