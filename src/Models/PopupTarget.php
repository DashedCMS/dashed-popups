<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PopupTarget extends Model
{
    protected $table = 'dashed__popup_targets';

    public const RULE_INCLUDE = 'include';
    public const RULE_EXCLUDE = 'exclude';

    public const MATCH_URL_PATTERN = 'url_pattern';
    public const MATCH_ALL_OF_TYPE = 'all_of_type';
    public const MATCH_SPECIFIC_MODEL = 'specific_model';

    protected $fillable = [
        'popup_id',
        'rule_type',
        'match_type',
        'pattern',
        'targetable_type',
        'targetable_id',
    ];

    protected $casts = [
        'targetable_id' => 'integer',
    ];

    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class);
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeIncludes(Builder $query): Builder
    {
        return $query->where('rule_type', self::RULE_INCLUDE);
    }

    public function scopeExcludes(Builder $query): Builder
    {
        return $query->where('rule_type', self::RULE_EXCLUDE);
    }
}
