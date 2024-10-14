<?php

namespace Dashed\DashedForms\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FormField extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $table = 'dashed__form_fields';

    public $translatable = [
        'name',
        'placeholder',
        'description',
        'helper_text',
        'options',
        'images',
    ];

    protected $casts = [
        'options' => 'array',
        'images' => 'array',
        'external_options' => 'array',
    ];

    protected $appends = [
        'fieldName',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function getFieldNameAttribute(): string
    {
        return str($this->name)->slug() . '-' . $this->id;
    }

    public function getLabelNameAttribute(): string
    {
        return $this->name . ($this->required ? '*' : '');
    }

    public function getPlaceholderNameAttribute(): string
    {
        return $this->placeholder ? $this->placeholder . ($this->required ? '*' : '') : '';
    }

    public function isImage(): bool
    {
        return ($this->type == 'select-image' || $this->type == 'file' || ($this->type == 'input' && $this->input_type == 'file'));
    }
}
