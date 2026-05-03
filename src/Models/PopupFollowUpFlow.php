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
}
