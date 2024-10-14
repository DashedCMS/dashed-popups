<?php

namespace Dashed\DashedForms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FormInputField extends Model
{
    use HasFactory;

    protected $table = 'dashed__form_input_fields';

    public function formInput(): BelongsTo
    {
        return $this->belongsTo(FormInput::class);
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }

    public function isImage(): bool
    {
        return $this->formField->isImage();
    }
}
