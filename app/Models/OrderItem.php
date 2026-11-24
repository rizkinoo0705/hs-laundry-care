<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'service_id', 'service_name', 'unit', 'qty', 'charged_qty', 'price', 'subtotal'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'charged_qty' => 'decimal:2',
            'price' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    /** True jika ditagih memakai berat/jumlah minimum. */
    public function usesMinimum(): bool
    {
        return $this->charged_qty !== null && (float) $this->charged_qty > (float) $this->qty;
    }

    public function billedQty(): float
    {
        return (float) ($this->charged_qty ?? $this->qty);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
