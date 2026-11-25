<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_handover_control_counts(): void
    {
        $kasir = User::create(['name' => 'Kasir', 'email' => 'k@test.id', 'password' => 'rahasia123', 'role' => 'kasir']);
        $c = Customer::create(['name' => 'Nia', 'phone' => '081313148938', 'address' => 'Jl. Melati 1']);
        $mitra = Mitra::create(['name' => 'Warung A']);
        $kurir = Staff::create(['name' => 'Andi', 'position' => 'kurir']);
        $n = 0;
        $o = function (array $a) use ($c, &$n) {
            return Order::create($a + ['code' => 'HS-'.(++$n), 'customer_id' => $c->id]);
        };

        // Siap diambil di outlet: datang sendiri + jemput saja
        $o(['status' => 'siap', 'delivery_method' => 'ambil']);
        $o(['status' => 'siap', 'delivery_method' => 'ambil', 'pickup' => true]);
        // Siap diantar: 1 belum lunas, 1 lunas tanpa kurir, 1 lunas sedang di jalan
        $o(['status' => 'siap', 'delivery_method' => 'antar', 'payment_status' => 'belum']);
        $a2 = $o(['status' => 'siap', 'delivery_method' => 'antar', 'payment_status' => 'lunas']);
        $a3 = $o(['status' => 'siap', 'delivery_method' => 'antar', 'payment_status' => 'lunas', 'pickup' => true]);
        Delivery::create(['type' => 'antar', 'order_id' => $a2->id, 'customer_id' => $c->id, 'address' => 'x', 'scheduled_at' => now()]);
        Delivery::create(['type' => 'antar', 'order_id' => $a3->id, 'customer_id' => $c->id, 'address' => 'x', 'scheduled_at' => now(), 'status' => 'jalan', 'staff_id' => $kurir->id]);
        // Perlu dijemput: 1 belum dijadwalkan, 1 terjadwal tanpa kurir
        $o(['status' => 'menunggu_timbang', 'pickup' => true]);
        $j2 = $o(['status' => 'menunggu_timbang', 'pickup' => true]);
        Delivery::create(['type' => 'jemput', 'order_id' => $j2->id, 'customer_id' => $c->id, 'address' => 'x', 'scheduled_at' => now()]);
        // Dropbox siap
        $o(['status' => 'siap', 'mitra_id' => $mitra->id]);
        // Tidak dihitung
        $o(['status' => 'selesai', 'delivery_method' => 'antar']);

        $res = $this->actingAs($kasir)->get(route('dashboard'))->assertOk()->assertSee('Kontrol serah terima');
        $control = $res->viewData('control');

        $this->assertSame(2, $control['outlet']);
        $this->assertSame(1, $control['dropbox']);
        $this->assertSame(['total' => 3, 'unpaid' => 1, 'no_courier' => 1, 'on_the_way' => 1], $control['antar']);
        $this->assertSame(['total' => 2, 'unscheduled' => 1, 'no_courier' => 1, 'on_the_way' => 0], $control['jemput']);

        // Link dari dashboard menyaring daftar order
        $this->actingAs($kasir)->get(route('orders.index', ['status' => 'siap', 'serah' => 'outlet']))
            ->assertOk()->assertSee('HS-1')->assertSee('HS-2')->assertDontSee('HS-4');
        $this->actingAs($kasir)->get(route('orders.index', ['status' => 'siap', 'serah' => 'dropbox']))
            ->assertOk()->assertSee('HS-8')->assertDontSee('HS-3');
    }
}
