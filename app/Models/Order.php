<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = [
        'menunggu_timbang' => 'Menunggu Timbang',
        'diterima' => 'Diterima',
        'dicuci' => 'Dicuci',
        'disetrika' => 'Disetrika',
        'siap' => 'Siap Diambil/Antar',
        'selesai' => 'Selesai',
        'batal' => 'Batal',
    ];

    /** Urutan proses normal. Boleh melompat maju, tidak boleh mundur. */
    public const FLOW = ['diterima', 'dicuci', 'disetrika', 'siap', 'selesai'];

    public const PAYMENT_METHODS = [
        'tunai' => 'Tunai',
        'transfer' => 'Transfer',
        'qris' => 'QRIS',
    ];

    /** Pilihan antar-jemput di form order (dipetakan ke pickup + delivery_method). */
    public const DELIVERY_MODES = [
        'none' => ['title' => 'Tidak', 'desc' => 'Pelanggan datang & ambil sendiri ke outlet', 'pickup' => false, 'method' => 'ambil'],
        'jemput' => ['title' => 'Jemput saja', 'desc' => 'Kurir jemput cucian; pelanggan ambil sendiri', 'pickup' => true, 'method' => 'ambil'],
        'antar' => ['title' => 'Antar saja', 'desc' => 'Pelanggan datang ke outlet; kurir antar hasilnya', 'pickup' => false, 'method' => 'antar'],
        'keduanya' => ['title' => 'Jemput + antar', 'desc' => 'Kurir jemput dan antar kembali (2 perjalanan)', 'pickup' => true, 'method' => 'antar'],
    ];

    /** Jenis order untuk daftar & filter (dihitung dari mitra, pickup, delivery_method). */
    public const SERVICE_TYPES = [
        'keduanya' => 'Antar-jemput customer',
        'dropbox' => 'Antar-jemput dropbox',
        'jemput' => 'Jemput saja',
        'antar' => 'Antar saja',
        'none' => 'Datang sendiri',
    ];

    public const DELIVERY_METHODS = [
        'ambil' => 'Diambil sendiri',
        'antar' => 'Diantar',
    ];

    protected $attributes = [
        'status' => 'diterima', 'delivery_method' => 'ambil', 'subtotal' => 0, 'discount' => 0,
        'total' => 0, 'total_kg' => 0, 'payment_status' => 'belum', 'stamp_awarded' => false, 'commission_amount' => 0,
        'pickup' => false, 'delivery_trips' => 0, 'delivery_fee' => 0, 'delivery_discount' => 0,
    ];

    protected $fillable = [
        'code', 'customer_id', 'mitra_id', 'user_id', 'staff_id', 'status', 'delivery_method',
        'subtotal', 'discount', 'total', 'total_kg', 'payment_status', 'payment_method',
        'paid_at', 'due_at', 'completed_at', 'notes', 'stamp_awarded',
        'commission_amount', 'mitra_payout_id',
        'pickup', 'distance_km', 'delivery_zone_id', 'delivery_trips',
        'delivery_fee', 'delivery_discount', 'delivery_note',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'stamp_awarded' => 'boolean',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'commission_amount' => 'integer',
            'total_kg' => 'decimal:2',
            'pickup' => 'boolean',
            'distance_km' => 'decimal:2',
            'delivery_trips' => 'integer',
            'delivery_fee' => 'integer',
            'delivery_discount' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function staffCommissions(): HasMany
    {
        return $this->hasMany(StaffCommission::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    /** Total cucian saja (tanpa ongkir). */
    public function laundryTotal(): int
    {
        return $this->subtotal - $this->discount;
    }

    public function deliveryNet(): int
    {
        return $this->delivery_fee - $this->delivery_discount;
    }

    public function tripsLabel(): string
    {
        $parts = [];
        if ($this->pickup) {
            $parts[] = 'jemput';
        }
        if ($this->delivery_method === 'antar') {
            $parts[] = 'antar';
        }

        return $parts ? ucfirst(implode(' + ', $parts)) : '-';
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(MitraPayout::class, 'mitra_payout_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'batal');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['selesai', 'batal']);
    }

    /** Order mitra yang komisinya sudah bisa dicairkan. */
    public function scopeCommissionable(Builder $query): Builder
    {
        return $query->whereNotNull('mitra_id')
            ->where('status', 'selesai')
            ->where('payment_status', 'lunas')
            ->whereNull('mitra_payout_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function serviceType(): string
    {
        if ($this->mitra_id) {
            return 'dropbox';
        }

        return match (true) {
            $this->pickup && $this->delivery_method === 'antar' => 'keduanya',
            $this->pickup => 'jemput',
            $this->delivery_method === 'antar' => 'antar',
            default => 'none',
        };
    }

    public function serviceTypeLabel(): string
    {
        return self::SERVICE_TYPES[$this->serviceType()];
    }

    public function scopeServiceType(Builder $query, string $type): Builder
    {
        return match ($type) {
            'dropbox' => $query->whereNotNull('mitra_id'),
            'keduanya' => $query->whereNull('mitra_id')->where('pickup', true)->where('delivery_method', 'antar'),
            'jemput' => $query->whereNull('mitra_id')->where('pickup', true)->where('delivery_method', '!=', 'antar'),
            'antar' => $query->whereNull('mitra_id')->where('pickup', false)->where('delivery_method', 'antar'),
            'none' => $query->whereNull('mitra_id')->where('pickup', false)->where('delivery_method', '!=', 'antar'),
            default => $query,
        };
    }

    /** Order antar (Antar saja / Jemput + antar) yang sudah siap DAN lunas: boleh diantar. */
    public function readyForDelivery(): bool
    {
        return $this->delivery_method === 'antar'
            && $this->status === 'siap'
            && $this->isPaid();
    }

    public function hasActiveDelivery(string $type): bool
    {
        return $this->deliveries()->where('type', $type)->where('status', '!=', 'batal')->exists();
    }

    public function awaitingWeighing(): bool
    {
        return $this->status === 'menunggu_timbang';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'lunas';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['selesai', 'batal'], true);
    }

    /** Status yang boleh dipilih berikutnya dari status sekarang. */
    public function allowedNextStatuses(): array
    {
        if ($this->isClosed()) {
            return [];
        }

        // Order jemput yang belum ditimbang: lanjut lewat form timbang, atau dibatalkan.
        if ($this->status === 'menunggu_timbang') {
            return ['batal'];
        }

        $index = array_search($this->status, self::FLOW, true);
        $next = array_slice(self::FLOW, $index + 1);
        $next[] = 'batal';

        return $next;
    }

    public function waMessage(): string
    {
        $lines = [
            'Halo '.$this->customer->name.',',
            '',
            'Cucian Anda di '.config('laundry.name').':',
            'No. order: '.$this->code,
            'Status: '.$this->statusLabel(),
            $this->status === 'menunggu_timbang'
                ? 'Total: menunggu cucian ditimbang di outlet'
                : 'Total: '.rupiah($this->total).' ('.($this->isPaid() ? 'LUNAS' : 'belum dibayar').')',
        ];

        if ($this->status === 'siap') {
            $lines[] = '';
            $lines[] = $this->delivery_method === 'antar'
                ? 'Cucian sudah siap dan akan segera kami antar.'
                : 'Cucian sudah siap diambil.';
        }

        $lines[] = '';
        $lines[] = 'Terima kasih - '.config('laundry.tagline');

        return implode("\n", $lines);
    }
}
