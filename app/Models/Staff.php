<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';

    public const POSITIONS = [
        'umum' => 'Umum',
        'produksi' => 'Produksi (cuci/setrika)',
        'kurir' => 'Kurir',
    ];

    protected $fillable = ['name', 'phone', 'position', 'active', 'notes'];

    protected $attributes = ['position' => 'umum', 'active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('name');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(StaffCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(StaffPayout::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function positionLabel(): string
    {
        return self::POSITIONS[$this->position] ?? $this->position;
    }

    public function unpaidCommission(): int
    {
        return (int) $this->commissions()->whereNull('staff_payout_id')->sum('amount');
    }
}
