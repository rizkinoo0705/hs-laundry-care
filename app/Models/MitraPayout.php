<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MitraPayout extends Model
{
    protected $fillable = ['mitra_id', 'amount', 'total_kg', 'orders_count', 'paid_at', 'paid_by', 'notes'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount' => 'integer',
            'total_kg' => 'decimal:2',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
