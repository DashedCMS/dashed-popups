<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopupView extends Model
{
    protected $table = 'dashed__popup_views';

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class);
    }
}
