<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class PopupFollowUpEmail extends Model
{
    use HasTranslations;

    protected $table = 'dashed__popup_follow_up_emails';

    protected $fillable = [
        'flow_id',
        'sort',
        'send_after_minutes',
        'is_active',
        'subject',
        'blocks',
    ];

    public array $translatable = [
        'subject',
        'blocks',
    ];

    protected $casts = [
        'send_after_minutes' => 'integer',
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(PopupFollowUpFlow::class, 'flow_id');
    }
}
