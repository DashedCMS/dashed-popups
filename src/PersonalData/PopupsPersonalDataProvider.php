<?php

declare(strict_types=1);

namespace Dashed\DashedPopups\PersonalData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder;
use Dashed\DashedCore\PersonalData\PersonalDataExport;
use Dashed\DashedCore\PersonalData\PersonalDataFinding;
use Dashed\DashedCore\PersonalData\PersonalDataSubject;
use Dashed\DashedCore\PersonalData\PersonalDataProvider;

/**
 * Popup-vertoningen bij een AVG-verzoek. Alleen vertoningen met een
 * e-mailadres of account zijn tot een persoon te herleiden; anonieme
 * vertoningen (alleen IP en browser) zijn niet op een e-mailadres te vinden.
 *
 * Anonimiseren en niet verwijderen: de rij telt mee in de cijfers per popup
 * (gecachte totalen en de dagaggregatie die de opruimronde vooraf bijwerkt),
 * en de koppeling aan een korting of bestelling is omzetrapportage. Alles
 * wat naar de persoon wijst gaat eruit: e-mail, account, IP, browser, sessie,
 * de ingevulde inhoud, URL en verwijzer. Een lopende follow-up wordt
 * afgebroken, zodat er niets meer gemaild wordt.
 */
class PopupsPersonalDataProvider extends PersonalDataProvider
{
    protected const TABLE = 'dashed__popup_views';

    public const ANONYMOUS_IP = '0.0.0.0';

    public function key(): string
    {
        return 'dashed-popups';
    }

    public function label(): string
    {
        return __('Popups');
    }

    public function find(PersonalDataSubject $subject): array
    {
        $views = $this->views($subject);
        if (! $views || ($count = (clone $views)->count()) === 0) {
            return [];
        }

        return [
            PersonalDataFinding::anonymize('popup_views', __('Popup-vertoningen met e-mailadres'), $count, __('De vertoning blijft meetellen in de popupcijfers; e-mail, account, IP-adres, browser en ingevulde gegevens worden gewist en een lopende follow-up stopt.')),
        ];
    }

    public function export(PersonalDataSubject $subject, PersonalDataExport $export): void
    {
        $views = $this->views($subject);
        if (! $views) {
            return;
        }

        $query = (clone $views)->select('v.*');
        if ($this->hasTable('dashed__popups')) {
            $query->leftJoin('dashed__popups as p', 'p.id', '=', 'v.popup_id')
                ->addSelect('p.name as popup_name');
        }

        $export->add('popup_views', $this->rows($query));
    }

    public function erase(PersonalDataSubject $subject): array
    {
        $views = $this->views($subject);
        if (! $views) {
            return [];
        }

        $ids = (clone $views)->pluck('v.id')->all();
        if ($ids === []) {
            return [];
        }

        $columns = array_flip(Schema::getColumnListing(self::TABLE));

        // Eerst de lopende follow-ups afbreken, op het moment dat
        // follow_up_started_at nog gezet is.
        if (isset($columns['follow_up_cancelled_at'], $columns['follow_up_started_at'])) {
            DB::table(self::TABLE)->whereIn('id', $ids)
                ->whereNotNull('follow_up_started_at')
                ->whereNull('follow_up_cancelled_at')
                ->update(['follow_up_cancelled_at' => now()]);
        }

        DB::table(self::TABLE)->whereIn('id', $ids)->update(array_intersect_key([
            'email' => null,
            'user_id' => null,
            // NOT NULL in de oorspronkelijke migratie, dus een vaste waarde.
            'ip_address' => self::ANONYMOUS_IP,
            'user_agent' => null,
            'session_id' => null,
            'content' => null,
            'url' => null,
            'referrer' => null,
            'updated_at' => now(),
        ], $columns));

        return [
            PersonalDataFinding::anonymize('popup_views', __('Popup-vertoningen met e-mailadres'), count($ids)),
        ];
    }

    protected function views(PersonalDataSubject $subject): ?Builder
    {
        if (! $this->hasTable(self::TABLE)) {
            return null;
        }

        $hasEmail = Schema::hasColumn(self::TABLE, 'email');
        $hasUser = $subject->hasUsers() && Schema::hasColumn(self::TABLE, 'user_id');

        if (! $hasEmail && ! $hasUser) {
            return null;
        }

        return DB::table(self::TABLE . ' as v')->where(function ($query) use ($subject, $hasEmail, $hasUser) {
            if ($hasEmail) {
                $subject->whereEmail($query, 'v.email');
            }
            if ($hasUser) {
                $query->orWhereIn('v.user_id', $subject->userIds());
            }
        });
    }
}
