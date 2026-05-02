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
    ];

    protected $casts = [
        'is_default' => 'boolean',
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

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }
}
