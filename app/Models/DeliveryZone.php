<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = ['name', 'max_km', 'fee', 'active'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return [
            'max_km' => 'decimal:2',
            'fee' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('max_km');
    }

    /** Zona terdekat yang mencakup jarak ini, atau null jika di luar semua zona. */
    public static function forDistance(float $km): ?self
    {
        return self::active()->where('max_km', '>=', $km)->first();
    }

    public function rangeLabel(?self $previous = null): string
    {
        $from = $previous ? qty($previous->max_km) : '0';

        return $from.'–'.qty($this->max_km).' km';
    }
}
