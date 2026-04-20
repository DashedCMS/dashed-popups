<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Popup extends Model
{
    use HasTranslations;

    protected $table = 'dashed__popups';

    protected $guarded = [];

    public array $translatable = ['title', 'blocks'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'active' => 'boolean',
        'auto_apply_discount' => 'boolean',
        'blocks' => 'array',
        'discount_percentage' => 'integer',
        'discount_valid_days' => 'integer',
        'trigger_value' => 'integer',
        'show_again_after' => 'integer',
        'notify_on_conversion' => 'boolean',
        'ai_analysis' => 'array',
        'ai_analyzed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function ($popup) {
            $popup->views()->delete();
        });

        static::saved(function ($popup) {
            if ($popup->active && $popup->wasChanged('active')) {
                static::where('id', '!=', $popup->id)
                    ->where('active', true)
                    ->update(['active' => false]);
            }
        });
    }

    public function views(): HasMany
    {
        return $this->hasMany(PopupView::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PopupVariant::class, 'popup_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(PopupView::class)->whereNotNull('submitted_at');
    }

    public function isDiscountType(): bool
    {
        return $this->type === 'discount';
    }

    public function isActiveNow(): bool
    {
        if (! $this->active) {
            return false;
        }

        $now = now();

        return $this->start_date->lte($now) && $this->end_date->gte($now);
    }
}
