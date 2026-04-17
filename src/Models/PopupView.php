<?php

namespace Dashed\DashedPopups\Models;

use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Illuminate\Database\Eloquent\Model;
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
        'discount_code_id' => 'integer',
    ];

    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }
}
