<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $hidden = [
        'stripe_payment_id',
        'stripe_invoice_id',
    ];

    protected $fillable = [
        'workspace_id',
        'subscription_id',
        'stripe_payment_id',
        'stripe_invoice_id',
        'gateway_slug',
        'gateway_transaction_id',
        'amount',
        'currency',
        'status',
        'description',
        'coupon_code',
        'discount_amount',
        'original_amount',
        'failure_reason',
        'refund_amount',
        'refunded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
