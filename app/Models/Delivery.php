<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    public const TYPES = [
        'jemput' => 'Jemput',
        'antar' => 'Antar',
    ];

    public const STATUSES = [
        'terjadwal' => 'Terjadwal',
        'jalan' => 'Dalam Perjalanan',
        'selesai' => 'Selesai',
        'batal' => 'Batal',
    ];

    protected $fillable = [
        'type', 'customer_id', 'order_id', 'mitra_id', 'address',
        'scheduled_at', 'courier_id', 'status', 'notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Nama tujuan: pelanggan atau mitra dropbox. */
    public function targetName(): string
    {
        return $this->customer?->name ?? $this->mitra?->name ?? '-';
    }

    public function targetPhone(): ?string
    {
        return $this->customer?->phone ?? $this->mitra?->phone;
    }
}
