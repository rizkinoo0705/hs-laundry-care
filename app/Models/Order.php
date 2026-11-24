<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = [
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

    public const DELIVERY_METHODS = [
        'ambil' => 'Diambil sendiri',
        'antar' => 'Diantar',
    ];

    protected $fillable = [
        'code', 'customer_id', 'mitra_id', 'user_id', 'status', 'delivery_method',
        'subtotal', 'discount', 'total', 'total_kg', 'payment_status', 'payment_method',
        'paid_at', 'due_at', 'completed_at', 'notes', 'stamp_awarded',
        'commission_amount', 'mitra_payout_id',
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
            'Total: '.rupiah($this->total).' ('.($this->isPaid() ? 'LUNAS' : 'belum dibayar').')',
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
