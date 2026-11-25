                <tr>
                    <td data-label="Jam" class="nowrap strong">{{ ! empty($showDate) ? $delivery->scheduled_at->translatedFormat('d M H:i') : $delivery->scheduled_at->format('H:i') }}</td>
                    <td data-label="Jenis"><span class="badge">{{ $delivery->typeLabel() }}</span></td>
                    <td data-label="Tujuan">
                        {{ $delivery->targetName() }} @if ($delivery->mitra)<span class="small muted">(mitra)</span>@endif<br>
                        <span class="small">{{ $delivery->address }}</span>
                        @if ($delivery->targetPhone())
                            <br><a href="{{ \App\Support\Phone::waLink($delivery->targetPhone()) }}" target="_blank" rel="noopener" class="small">{{ \App\Support\Phone::format($delivery->targetPhone()) }}</a>
                        @endif
                        @if ($delivery->distance_km !== null)<br><span class="small muted">{{ qty($delivery->distance_km) }} km dari outlet</span>@endif
                        @if ($delivery->notes)<br><span class="small muted">{{ $delivery->notes }}</span>@endif
                        @if ($delivery->directionsUrl())<br><a href="{{ $delivery->directionsUrl() }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm" style="margin-top: 4px">Rute (Google Maps)</a>@endif
                    </td>
                    <td data-label="Order">@if ($delivery->order)<a href="{{ route('orders.show', $delivery->order) }}">{{ $delivery->order->code }}</a>@else - @endif</td>
                    <td data-label="Status"><span class="badge badge-{{ $delivery->status }}">{{ $delivery->statusLabel() }}</span>
                        @if ($delivery->staff)<br><span class="small muted">Kurir: {{ $delivery->staff->name }}</span>@endif</td>
                    <td data-label="Ubah">
                        <form method="POST" action="{{ route('deliveries.status', $delivery) }}" class="inline-form">
                            @csrf @method('PATCH')
                            <select name="status" aria-label="Status">
                                @foreach (\App\Models\Delivery::STATUSES as $key => $label)
                                    <option value="{{ $key }}" @selected($delivery->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="staff_id" aria-label="Kurir">
                                <option value="">Pilih kurir</option>
                                @foreach ($couriers as $c)
                                    <option value="{{ $c->id }}" @selected($delivery->staff_id === $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm">Simpan</button>
                        </form>
                    </td>
                </tr>
