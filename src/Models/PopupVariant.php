<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PopupVariant extends Model
{
    protected $table = 'dashed__popup_variants';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'split_weight' => 'integer',
        'discount_percentage_override' => 'integer',
        'discount_valid_days_override' => 'integer',
        'sort_order' => 'integer',
    ];

    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class, 'popup_id');
    }

    public function popupViews(): HasMany
    {
        return $this->hasMany(PopupView::class, 'variant_id');
    }

    public static function pickForPopup(int $popupId): ?self
    {
        $variants = self::query()
            ->where('popup_id', $popupId)
            ->where('enabled', true)
            ->where('split_weight', '>', 0)
            ->orderBy('sort_order')
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $total = (int) $variants->sum('split_weight');

        if ($total <= 0) {
            return null;
        }

        $pick = random_int(1, $total);
        $running = 0;

        foreach ($variants as $variant) {
            $running += (int) $variant->split_weight;
            if ($pick <= $running) {
                return $variant;
            }
        }

        return $variants->last();
    }

    public function resolvedDiscountPercentage(): int
    {
        return (int) ($this->discount_percentage_override ?? $this->popup->discount_percentage ?? 0);
    }

    public function resolvedValidDays(): int
    {
        return (int) ($this->discount_valid_days_override ?? $this->popup->discount_valid_days ?? 14);
    }
}
