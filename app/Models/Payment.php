<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'gateway',
        'status',
        'amount',
        'currency',
        'transaction_id',
        'gateway_reference',
        'checkout_request_id',
        'gateway_response',
        'webhook_data',
        'failure_reason',
        'paid_at',
        'failed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'webhook_data' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByGateway($query, $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isProcessing()
    {
        return $this->status === 'processing';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function markAsProcessing()
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted($transactionId = null)
    {
        $this->update([
            'status' => 'completed',
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'paid_at' => now(),
        ]);

        $this->order->update([
            'payment_status' => 'paid',
            'transaction_reference' => $this->transaction_id,
        ]);
    }

    public function markAsFailed($reason = null)
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'failed_at' => now(),
        ]);

        $this->order->update([
            'payment_status' => 'failed',
        ]);
    }

    public function markAsCancelled()
    {
        $this->update(['status' => 'cancelled']);

        $this->order->update([
            'payment_status' => 'failed',
        ]);
    }

    public function canBeProcessed()
    {
        return $this->isPending();
    }

    public function canBeCompleted()
    {
        return $this->isPending() || $this->isProcessing();
    }

    public function canBeFailed()
    {
        return $this->isPending() || $this->isProcessing();
    }
}