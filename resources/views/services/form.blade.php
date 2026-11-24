@extends('layouts.app')
@section('title', $service->exists ? 'Ubah Layanan' : 'Layanan Baru')

@section('content')
<div class="page-head">
    <h1>{{ $service->exists ? 'Ubah Layanan' : 'Layanan Baru' }}</h1>
</div>

<div class="card" style="max-width: 640px">
    <form method="POST" action="{{ $service->exists ? route('services.update', $service) : route('services.store') }}">
        @csrf
        @if ($service->exists) @method('PUT') @endif

        <div class="field">
            <label for="name">Nama layanan</label>
            <input type="text" id="name" name="name" required value="{{ old('name', $service->name) }}">
        </div>
        <div class="grid grid-2" style="gap: 12px">
            <div class="field">
                <label for="category">Kategori</label>
                <select id="category" name="category">
                    @foreach (\App\Models\Service::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $service->category) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="unit">Satuan</label>
                <select id="unit" name="unit">
                    @foreach (\App\Models\Service::UNITS as $key => $label)
                        <option value="{{ $key }}" @selected(old('unit', $service->unit) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="price">Harga (Rp per satuan)</label>
                <input type="number" id="price" name="price" min="0" step="500" required value="{{ old('price', $service->price) }}" inputmode="numeric">
            </div>
            <div class="field">
                <label for="min_qty">Minimum ditagih (<span id="min-unit">{{ old('unit', $service->unit) ?: 'kg' }}</span>)</label>
                <input type="number" id="min_qty" name="min_qty" min="0" step="0.5" value="{{ old('min_qty', $service->hasMinimum() ? qty($service->min_qty) : '') }}" inputmode="decimal" placeholder="Kosongkan jika tanpa minimum">
                <div class="hint">Mis. 3: cucian 2,5 kg ditagih 3 kg; 4 kg ditagih 4 kg.</div>
            </div>
            <div class="field">
                <label for="duration_hours">Estimasi selesai (jam)</label>
                <input type="number" id="duration_hours" name="duration_hours" min="1" required value="{{ old('duration_hours', $service->duration_hours) }}" inputmode="numeric">
            </div>
        </div>
        <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $service->active))> Aktif (muncul di form order)</label>

        <div class="row" style="margin-top: 10px">
            <button type="submit" class="btn">Simpan</button>
            <a href="{{ route('services.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('unit').addEventListener('change', function () {
    document.getElementById('min-unit').textContent = this.value;
});
</script>
@endpush
