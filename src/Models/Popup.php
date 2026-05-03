<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedPopups\Services\PopupTargetingService;

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
        'discount_percentage' => 'decimal:2',
        'discount_valid_days' => 'integer',
        'trigger_value' => 'integer',
        'show_again_after' => 'integer',
        'notify_on_conversion' => 'boolean',
        'ai_analysis' => 'array',
        'ai_analyzed_at' => 'datetime',
        'api_subscriptions' => 'array',
        'follow_up_flow_id' => 'integer',
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

    public function targets(): HasMany
    {
        return $this->hasMany(PopupTarget::class, 'popup_id');
    }

    public function includeTargets(): HasMany
    {
        return $this->hasMany(PopupTarget::class, 'popup_id')->where('rule_type', PopupTarget::RULE_INCLUDE);
    }

    public function excludeTargets(): HasMany
    {
        return $this->hasMany(PopupTarget::class, 'popup_id')->where('rule_type', PopupTarget::RULE_EXCLUDE);
    }

    public function shouldShowFor(Request $request): bool
    {
        return app(PopupTargetingService::class)->shouldShow($this, $request);
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

    public function followUpFlow(): BelongsTo
    {
        return $this->belongsTo(PopupFollowUpFlow::class, 'follow_up_flow_id');
    }

    /**
     * Resolve the follow-up flow that should run when a visitor submits this
     * popup. Returns the popup's own flow if set, otherwise the default flow,
     * otherwise null (no flow runs).
     */
    public function resolveFollowUpFlow(): ?PopupFollowUpFlow
    {
        if ($this->follow_up_flow_id) {
            $flow = PopupFollowUpFlow::find($this->follow_up_flow_id);

            return ($flow && $flow->is_active) ? $flow : null;
        }

        return PopupFollowUpFlow::default();
    }
}
