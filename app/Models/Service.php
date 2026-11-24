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

    protected $fillable = ['name', 'category', 'unit', 'price', 'duration_hours', 'active'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_hours' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
