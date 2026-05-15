<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopupVariant extends Model
{
    protected $table = 'dashed__popup_variants';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'split_weight' => 'integer',
        'discount_percentage_override' => 'decimal:2',
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

    public function resolvedDiscountPercentage(): float
    {
        return (float) ($this->discount_percentage_override ?? $this->popup->discount_percentage ?? 0);
    }

    public function resolvedValidDays(): int
    {
        return (int) ($this->discount_valid_days_override ?? $this->popup->discount_valid_days ?? 14);
    }

    /**
     * Inspect the parent popup's targets for a MATCH_RECOMMENDATION_STRATEGY
     * entry and, if present, ask the RecommendationService for products.
     * Returns an empty collection when the popup has no recommendation
     * target (or when the recommendation engine isn't installed yet).
     *
     * Consumers (popup-rendering controllers/components) read this as
     * `$variant->recommendedProducts` to surface a product strip inside
     * the popup body.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRecommendedProductsAttribute()
    {
        if (! class_exists(\Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationService::class)) {
            return collect();
        }

        $popup = $this->popup;
        if (! $popup) {
            return collect();
        }

        $hasTarget = false;
        foreach ($popup->targets ?? [] as $target) {
            if (($target->match_type ?? null) === PopupTarget::MATCH_RECOMMENDATION_STRATEGY) {
                $hasTarget = true;

                break;
            }
        }

        if (! $hasTarget) {
            return collect();
        }

        try {
            $context = \Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext::for(
                \Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement::Popup,
            )->withLimit(4)->build();

            return app(\Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationService::class)
                ->for($context)
                ->products;
        } catch (\Throwable) {
            return collect();
        }
    }
}
