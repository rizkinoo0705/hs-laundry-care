<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota {{ $order->code }}</title>
    <style>
        /* Ukuran nota printer thermal 58mm */
        @page { size: 58mm auto; margin: 3mm; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; margin: 0; }
        .nota { width: 52mm; margin: 0 auto; padding: 8px 0; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .big { font-size: 15px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.r { text-align: right; white-space: nowrap; }
        .no-print { text-align: center; margin: 16px 0; }
        .no-print button { font-size: 16px; padding: 10px 20px; min-height: 44px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="nota">
    <div class="center bold big">{{ config('laundry.name') }}</div>
    <div class="center">{{ config('laundry.tagline') }}</div>
    @if (config('laundry.address'))<div class="center">{{ config('laundry.address') }}</div>@endif
    <div class="center">WA {{ config('laundry.wa_number') }}</div>
    <hr>
    <table>
        <tr><td>No</td><td class="r bold">{{ $order->code }}</td></tr>
        <tr><td>Tgl</td><td class="r">{{ $order->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Plgn</td><td class="r">{{ $order->customer->name }}</td></tr>
        @if ($order->customer->member_code)<tr><td>Member</td><td class="r">{{ $order->customer->member_code }}</td></tr>@endif
        @if ($order->due_at)<tr><td>Selesai</td><td class="r">{{ $order->due_at->format('d/m/Y H:i') }}</td></tr>@endif
    </table>
    <hr>
    @if ($order->awaitingWeighing())
        <div class="center bold">CUCIAN DIJEMPUT</div>
        <div class="center">Berat &amp; total menyusul setelah ditimbang di outlet.</div>
    @endif
    <table>
        @foreach ($order->items as $item)
            <tr><td colspan="2">{{ $item->service_name }}</td></tr>
            @if ($item->usesMinimum())
                <tr><td colspan="2">Berat {{ qty($item->qty) }}{{ $item->unit }}, min. {{ qty($item->charged_qty) }}{{ $item->unit }}</td></tr>
            @endif
            <tr><td>{{ qty($item->billedQty()) }}{{ $item->unit }} x {{ number_format($item->price, 0, ',', '.') }}</td><td class="r">{{ number_format($item->subtotal, 0, ',', '.') }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        @if ($order->discount > 0 || $order->delivery_trips > 0)
            <tr><td>Subtotal</td><td class="r">{{ number_format($order->subtotal, 0, ',', '.') }}</td></tr>
            @if ($order->discount > 0)
                <tr><td>Diskon</td><td class="r">-{{ number_format($order->discount, 0, ',', '.') }}</td></tr>
            @endif
        @endif
        @if ($order->delivery_trips > 0)
            <tr><td>Ongkir ({{ strtolower($order->tripsLabel()) }})</td><td class="r">{{ number_format($order->delivery_fee, 0, ',', '.') }}</td></tr>
            @if ($order->delivery_discount > 0)
                <tr><td>{{ $order->delivery_note }}</td><td class="r">-{{ number_format($order->delivery_discount, 0, ',', '.') }}</td></tr>
            @endif
        @endif
        <tr class="bold"><td>TOTAL</td><td class="r">{{ rupiah($order->total) }}</td></tr>
        <tr><td>Status</td><td class="r bold">{{ $order->isPaid() ? 'LUNAS' : 'BELUM BAYAR' }}</td></tr>
    </table>
    @if ($order->notes)
        <hr><div>Catatan: {{ $order->notes }}</div>
    @endif
    @if ($order->customer->is_member)
        <hr><div class="center">Stempel member: {{ $order->customer->stamps }}/{{ config('laundry.stamp_target') }}</div>
    @endif
    <hr>
    <div class="center">Terima kasih!</div>
    <div class="center">Simpan nota ini untuk pengambilan.</div>
</div>
<div class="no-print"><button type="button" onclick="window.print()">Cetak</button></div>
</body>
</html>
