<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mitra extends Model
{
    public const COMMISSION_TYPES = [
        'per_kg' => 'Rupiah per kg',
        'percent' => 'Persen dari total',
    ];

    protected $fillable = [
        'name', 'owner_name', 'phone', 'address',
        'commission_type', 'commission_value', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'commission_value' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(MitraPayout::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Hitung komisi untuk satu order. */
    public function commissionFor(float $totalKg, int $total): int
    {
        if ($this->commission_type === 'percent') {
            return (int) round($total * $this->commission_value / 100);
        }

        return (int) round($totalKg * $this->commission_value);
    }

    public function commissionLabel(): string
    {
        return $this->commission_type === 'percent'
            ? $this->commission_value.'% dari total'
            : rupiah($this->commission_value).' / kg';
    }

    /** Komisi yang sudah boleh dibayar (order selesai & lunas, belum dicairkan). */
    public function unpaidCommission(): int
    {
        return (int) $this->orders()->commissionable()->sum('commission_amount');
    }
}
