<?php

namespace App\Models;

use App\Support\Geo;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const DELIVERY_PROMOS = [
        'none' => 'Tidak ada',
        'free' => 'Gratis ongkir',
        'percent' => 'Diskon ongkir (%)',
    ];

    protected $attributes = ['is_member' => false, 'stamps' => 0, 'delivery_promo' => 'none', 'delivery_promo_value' => 0];

    protected $fillable = [
        'name', 'phone', 'address', 'is_member', 'member_code',
        'member_since', 'stamps', 'notes',
        'latitude', 'longitude', 'distance_km', 'distance_source',
        'delivery_promo', 'delivery_promo_value', 'delivery_promo_until',
    ];

    protected function casts(): array
    {
        return [
            'is_member' => 'boolean',
            'member_since' => 'date',
            'stamps' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_km' => 'decimal:2',
            'delivery_promo_value' => 'integer',
            'delivery_promo_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->phone = Phone::normalize($customer->phone);
        });

        static::saved(function (Customer $customer) {
            if ($customer->is_member && ! $customer->member_code) {
                $customer->member_code = 'HS-M'.str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT);
                $customer->member_since ??= now();
                $customer->saveQuietly();
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function stampTransactions(): HasMany
    {
        return $this->hasMany(StampTransaction::class);
    }

    public function canRedeem(): bool
    {
        return $this->is_member && $this->stamps >= config('laundry.stamp_target');
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Jarak garis lurus dari outlet (km), null bila lokasi belum lengkap. */
    public function straightLineKm(): ?float
    {
        $outlet = Setting::outletLocation();

        if (! $outlet || ! $this->hasLocation()) {
            return null;
        }

        return round(Geo::haversineKm($outlet[0], $outlet[1], $this->latitude, $this->longitude), 2);
    }

    public function hasActiveDeliveryPromo(): bool
    {
        if ($this->delivery_promo === 'none' || ! $this->delivery_promo) {
            return false;
        }

        if ($this->delivery_promo === 'percent' && $this->delivery_promo_value <= 0) {
            return false;
        }

        return ! $this->delivery_promo_until || $this->delivery_promo_until->endOfDay()->isFuture();
    }

    public function deliveryPromoLabel(): ?string
    {
        if (! $this->hasActiveDeliveryPromo()) {
            return null;
        }

        $label = $this->delivery_promo === 'free'
            ? 'Gratis ongkir'
            : 'Diskon ongkir '.$this->delivery_promo_value.'%';

        return $this->delivery_promo_until
            ? $label.' s.d. '.$this->delivery_promo_until->translatedFormat('d M Y')
            : $label;
    }

    public function directionsUrl(): ?string
    {
        return Geo::directionsUrl($this->latitude, $this->longitude, $this->address);
    }

    public function waLink(string $message = ''): string
    {
        return Phone::waLink($this->phone, $message);
    }
}
