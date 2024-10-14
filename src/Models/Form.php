<?php

namespace Dashed\DashedForms\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;

class Form extends Model
{
    use LogsActivity;
    use HasTranslations;
    use HasCustomBlocks;

    protected static $logFillable = true;

    protected $table = 'dashed__forms';

    protected static function booted()
    {
        static::deleting(function ($form) {
            $form->fields()->delete();
            $form->inputs()->delete();
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
        'redirect_after_form' => 'array',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)
            ->orderBy('sort');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(FormInput::class);
    }

    public function emailConfirmationFormField(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'email_confirmation_form_field_id');
    }

    public function scopeSearch($query, ?string $search = null)
    {
        if (request()->get('search') ?: $search) {
            $search = strtolower(request()->get('search') ?: $search);

            return $query->where('name', 'LIKE', '%' . $search . '%');
        }
    }
}
