<?php

namespace Dashed\DashedPopups\Models;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
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
        'newsletter_synced_at' => 'datetime',
        'follow_up_started_at' => 'datetime',
        'follow_up_cancelled_at' => 'datetime',
        'discount_code_id' => 'integer',
        'matched_order_id' => 'integer',
        'user_id' => 'integer',
        'content' => 'array',
    ];

    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PopupVariant::class, 'variant_id');
    }

    public function matchedOrder(): ?\Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        if (! class_exists(\Dashed\DashedEcommerceCore\Models\Order::class)) {
            return null;
        }

        return $this->belongsTo(\Dashed\DashedEcommerceCore\Models\Order::class, 'matched_order_id');
    }

    /**
     * Beschrijft waar deze PopupView zit binnen zijn follow-up flow op dit moment.
     *
     * Mogelijke return-waarden:
     * - 'not_in_flow': geen email ingevuld of follow-up nooit gestart
     * - 'cancelled': flow handmatig geannuleerd (bv. na conversie)
     * - 'finished': alle emails van de flow zouden inmiddels verstuurd moeten zijn
     * - 'step_X_of_Y': momenteel wachtend op email-stap X (1-indexed)
     */
    public function followUpStatus(): string
    {
        if ($this->follow_up_cancelled_at !== null) {
            return 'cancelled';
        }

        if ($this->follow_up_started_at === null) {
            return 'not_in_flow';
        }

        $popup = $this->popup;
        $flow = $popup?->resolveFollowUpFlow();

        if (! $flow) {
            return 'finished';
        }

        $emails = $flow->activeEmails()->orderBy('send_after_minutes')->get();
        $totalSteps = $emails->count();

        if ($totalSteps === 0) {
            return 'finished';
        }

        $elapsedMinutes = $this->follow_up_started_at->diffInMinutes(now());

        $nextStep = null;
        foreach ($emails as $i => $email) {
            if ($elapsedMinutes < (int) $email->send_after_minutes) {
                $nextStep = $i + 1;

                break;
            }
        }

        if ($nextStep === null) {
            return 'finished';
        }

        return "step_{$nextStep}_of_{$totalSteps}";
    }
}
