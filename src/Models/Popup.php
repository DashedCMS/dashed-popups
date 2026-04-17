<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

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
    ];

    protected static function booted(): void
    {
        static::deleting(function ($popup) {
            $popup->views()->delete();
        });
    }

    public function views(): HasMany
    {
        return $this->hasMany(PopupView::class);
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
