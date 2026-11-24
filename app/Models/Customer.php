<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'address', 'is_member', 'member_code',
        'member_since', 'stamps', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_member' => 'boolean',
            'member_since' => 'date',
            'stamps' => 'integer',
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

    public function waLink(string $message = ''): string
    {
        return Phone::waLink($this->phone, $message);
    }
}
