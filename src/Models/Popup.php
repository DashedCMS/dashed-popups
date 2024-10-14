<?php

namespace Dashed\DashedPopups\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;

class Popup extends Model
{
    use LogsActivity;
    use HasTranslations;
    use HasCustomBlocks;

    protected static $logFillable = true;

    protected $table = 'dashed__popups';

    protected static function booted()
    {
        static::deleting(function ($popup) {
            $popup->fields()->delete();
            $popup->inputs()->delete();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public $translatable = [
        'mustHaveSomethingDefined',
    ];

    protected $casts = [
        'external_options' => 'array',
        'redirect_after_popup' => 'array',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(PopupField::class)
            ->orderBy('sort');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(PopupInput::class);
    }

    public function emailConfirmationPopupField(): BelongsTo
    {
        return $this->belongsTo(PopupField::class, 'email_confirmation_popup_field_id');
    }

    public function scopeSearch($query, ?string $search = null)
    {
        if (request()->get('search') ?: $search) {
            $search = strtolower(request()->get('search') ?: $search);

            return $query->where('name', 'LIKE', '%' . $search . '%');
        }
    }
}
