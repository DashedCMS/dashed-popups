<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopupView extends Model
{
    protected $table = 'dashed__popup_views';

    protected $guarded = [];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'closed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'newsletter_synced_at' => 'datetime',
        'follow_up_started_at' => 'datetime',
        'follow_up_cancelled_at' => 'datetime',
        'discount_code_id' => 'integer',
        'matched_order_id' => 'integer',
        'user_id' => 'integer',
        'content' => 'array',
    ];

    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PopupVariant::class, 'variant_id');
    }

    public function matchedOrder(): ?\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        if (! class_exists(\Dashed\DashedEcommerceCore\Models\Order::class)) {
            return null;
        }

        return $this->belongsTo(\Dashed\DashedEcommerceCore\Models\Order::class, 'matched_order_id');
    }
}
