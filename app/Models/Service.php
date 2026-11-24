<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public const CATEGORIES = [
        'kiloan' => 'Reguler Kiloan',
        'gosok' => 'Gosok Saja',
        'premium' => 'Premium',
        'satuan' => 'Satuan',
    ];

    public const UNITS = [
        'kg' => 'kg',
        'pcs' => 'pcs',
    ];

    protected $attributes = ['duration_hours' => 48, 'active' => true];

    protected $fillable = ['name', 'category', 'unit', 'price', 'min_qty', 'duration_hours', 'active'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'min_qty' => 'decimal:2',
            'duration_hours' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function hasMinimum(): bool
    {
        return $this->min_qty !== null && (float) $this->min_qty > 0;
    }

    /** Jumlah yang ditagih: minimal sebesar min_qty. */
    public function chargedQty(float $qty): float
    {
        return $this->hasMinimum() ? max($qty, (float) $this->min_qty) : $qty;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
