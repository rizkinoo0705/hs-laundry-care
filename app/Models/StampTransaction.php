<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampTransaction extends Model
{
    protected $fillable = ['customer_id', 'order_id', 'user_id', 'change', 'balance_after', 'note'];

    protected function casts(): array
    {
        return [
            'change' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
