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

        $locale = app()->getLocale();

        $buildBlocks = function (array $items): array {
            $blocks = [];
            foreach ($items as $item) {
                $blocks[(string) \Illuminate\Support\Str::uuid()] = $item;
            }

            return $blocks;
        };

        $emails = [
            [
                'sort' => 1,
                'send_after_minutes' => 60,
                'is_active' => true,
                'subject' => 'Bedankt voor je interesse - hier is je code :discountCode:',
                'blocks' => $buildBlocks([
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Hi,</p><p>Bedankt voor je inschrijving bij <strong>:siteName:</strong>! Je krijgt <strong>:discountValue:</strong> korting met de code <strong>:discountCode:</strong>:</p>']],
                    ['type' => 'discount', 'data' => ['label' => 'Gebruik deze code voor extra korting:', 'code' => '']],
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Plaats je bestelling snel - de code is een beperkte tijd geldig. Veel plezier met shoppen!</p>']],
                    ['type' => 'button', 'data' => ['label' => 'Bekijk onze producten', 'url' => ':siteUrl:']],
                ]),
            ],
            [
                'sort' => 2,
                'send_after_minutes' => 60 * 24,
                'is_active' => true,
                'subject' => 'Vergeet je korting niet (:discountCode:)',
                'blocks' => $buildBlocks([
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Je hebt nog <strong>:discountValue:</strong> korting (code <strong>:discountCode:</strong>) klaarstaan. Bekijk onze bestsellers en gebruik je code:</p>']],
                    ['type' => 'discount', 'data' => ['label' => 'Gebruik deze code voor extra korting:', 'code' => '']],
                    ['type' => 'button', 'data' => ['label' => 'Shoppen bij :siteName:', 'url' => ':siteUrl:']],
                ]),
            ],
            [
                'sort' => 3,
                'send_after_minutes' => 60 * 72,
                'is_active' => true,
                'subject' => 'Laatste kans: je code :discountCode: verloopt binnenkort',
                'blocks' => $buildBlocks([
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Dit is je laatste herinnering. Je <strong>:discountValue:</strong> kortingscode <strong>:discountCode:</strong> loopt binnenkort af:</p>']],
                    ['type' => 'discount', 'data' => ['label' => 'Gebruik deze code voor extra korting:', 'code' => '']],
                    ['type' => 'paragraph', 'data' => ['content' => '<p>Plaats vandaag nog je bestelling om er gebruik van te maken.</p>']],
                    ['type' => 'button', 'data' => ['label' => 'Naar de website', 'url' => ':siteUrl:']],
                ]),
            ],
        ];

        foreach ($emails as $emailData) {
            $email = $flow->emails()->make([
                'sort' => $emailData['sort'],
                'send_after_minutes' => $emailData['send_after_minutes'],
                'is_active' => $emailData['is_active'],
            ]);
            $email->setTranslation('subject', $locale, $emailData['subject']);
            $email->setTranslation('blocks', $locale, $emailData['blocks']);
            $email->save();
        }

        return $flow;
    }
}
