<?php

namespace Dashed\DashedPopups\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;

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
