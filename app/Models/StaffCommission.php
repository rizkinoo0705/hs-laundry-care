<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCommission extends Model
{
    public const TYPES = [
        'layanan' => 'Pengerjaan',
        'jemput' => 'Jemput',
        'antar' => 'Antar',
    ];

    protected $fillable = [
        'staff_id', 'order_id', 'delivery_id', 'type', 'description',
        'qty', 'rate', 'amount', 'earned_at', 'staff_payout_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'rate' => 'integer',
            'amount' => 'integer',
            'earned_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(StaffPayout::class, 'staff_payout_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
