<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PopupFollowUpFlow extends Model
{
    protected $table = 'dashed__popup_follow_up_flows';

    protected $fillable = [
        'name',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (PopupFollowUpFlow $flow) {
            if ($flow->is_default && $flow->wasChanged('is_default')) {
                static::query()
                    ->where('id', '!=', $flow->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // Mirror AbandonedCartFlow: maximaal 1 actieve flow tegelijk.
            if ($flow->is_active && $flow->wasChanged('is_active')) {
                static::query()
                    ->where('id', '!=', $flow->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }

    public function emails(): HasMany
    {
        return $this->hasMany(PopupFollowUpEmail::class, 'flow_id')->orderBy('sort');
    }

    public function activeEmails(): HasMany
    {
        return $this->hasMany(PopupFollowUpEmail::class, 'flow_id')
            ->where('is_active', true)
            ->orderBy('sort');
    }

    /**
     * De flow die als default geldt voor popups zonder eigen flow_id.
     * Vereist dat de flow ook actief is.
     */
    public static function default(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Maakt een complete standaard-flow aan met 3 zinnige opvolg-stappen
     * (1 uur, 24 uur, 72 uur na conversie) en zet deze direct op
     * is_active + is_default. Andere flows worden automatisch op inactive
     * en niet-default gezet door de booted() saved-hook.
     */
    public static function createDefault(): self
    {
        $flow = static::create([
            'name' => 'Standaard flow',
            'is_active' => true,
            'is_default' => true,
        ]);

        $emails = [
            [
                'sort' => 1,
                'send_after_minutes' => 60,
                'is_active' => true,
                'subject' => ['nl' => 'Bedankt voor je interesse'],
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Hi,</p><p>Bedankt voor je inschrijving bij <strong>:siteName:</strong>! Hierbij nogmaals je kortingscode:</p>']],
                    ['type' => 'discount', 'data' => ['label' => 'Gebruik deze code voor extra korting:', 'code' => '']],
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Plaats je bestelling snel — de code is een beperkte tijd geldig. Veel plezier met shoppen!</p>']],
                ],
            ],
            [
                'sort' => 2,
                'send_after_minutes' => 60 * 24,
                'is_active' => true,
                'subject' => ['nl' => 'Vergeet je korting niet'],
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Je hebt nog een mooie korting klaarstaan. Bekijk onze bestsellers en gebruik je code:</p>']],
                    ['type' => 'discount', 'data' => ['label' => 'Gebruik deze code voor extra korting:', 'code' => '']],
                ],
            ],
            [
                'sort' => 3,
                'send_after_minutes' => 60 * 72,
                'is_active' => true,
                'subject' => ['nl' => 'Laatste kans: je korting verloopt binnenkort'],
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Dit is je laatste herinnering. Je kortingscode loopt binnenkort af:</p>']],
                    ['type' => 'discount', 'data' => ['label' => 'Gebruik deze code voor extra korting:', 'code' => '']],
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Plaats vandaag nog je bestelling om er gebruik van te maken.</p>']],
                ],
            ],
        ];

        foreach ($emails as $email) {
            $flow->emails()->create($email);
        }

        return $flow;
    }
}
