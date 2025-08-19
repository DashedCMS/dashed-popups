<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Popup extends Model
{
    protected $table = 'dashed__popups';

    protected static function booted()
    {
        static::deleting(function ($popup) {
            $popup->views()->delete();
        });
    }

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function views(): HasMany
    {
        return $this->hasMany(PopupView::class);
    }
}
