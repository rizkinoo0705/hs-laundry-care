<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeighLaterAndMitraOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private Service $reguler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::create(['name' => 'Kasir', 'email' => 'k@test.id', 'password' => 'rahasia123', 'role' => 'kasir']);
        $this->reguler = Service::create(['name' => 'Cuci Gosok Reguler', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 7000, 'min_qty' => 3]);

        Setting::set('outlet_lat', '-6.1753924');
        Setting::set('outlet_lng', '106.8271528');
        DeliveryZone::create(['name' => 'Dekat', 'max_km' => 2, 'fee' => 5000]);
        Customer::create(['name' => 'Nia', 'phone' => '081313148938', 'address' => 'Jl. Melati 1',
            'latitude' => -6.18, 'longitude' => 106.83, 'distance_km' => 1.5, 'distance_source' => 'jalan']);
    }

    private function createJemputOrder(string $mode = 'keduanya'): Order
    {
        $this->actingAs($this->kasir)->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '081313148938',
            'delivery_mode' => $mode,
            'weigh_later' => '1',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    public function test_jemput_order_can_be_created_without_weight_then_weighed(): void
    {
        $order = $this->createJemputOrder();

        $this->assertSame('menunggu_timbang', $order->status);
        $this->assertCount(0, $order->items);
        $this->assertSame(10000, $order->delivery_fee);   // jemput + antar
        $this->assertSame(10000, $order->total);          // baru ongkir
        $this->assertSame(['batal'], $order->allowedNextStatuses());

        // Belum bisa dibayar & muncul di daftar jemput
        $this->actingAs($this->kasir)->patch(route('orders.pay', $order), ['payment_method' => 'tunai'])->assertSessionHasErrors();
        $this->actingAs($this->kasir)->get(route('deliveries.create'))->assertSee($order->code);
        $this->actingAs($this->kasir)->get(route('orders.show', $order))->assertOk()->assertSee('Timbang &amp; Isi Layanan', false);
        $this->actingAs($this->kasir)->get(route('orders.receipt', $order))->assertOk()->assertSee('CUCIAN DIJEMPUT');

        // Timbang di outlet: 2,5 kg -> ditagih minimum 3 kg
        $this->actingAs($this->kasir)->patch(route('orders.items', $order), [
            'items' => [['service_id' => $this->reguler->id, 'qty' => '2.5']],
        ])->assertSessionHasNoErrors()->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('diterima', $order->status);
        $this->assertSame(21000, $order->subtotal);
        $this->assertSame(21000 + 10000, $order->total);
        $this->assertEquals(2.5, (float) $order->total_kg);
        $this->assertNotNull($order->due_at);

        // Tidak bisa ditimbang dua kali
        $this->actingAs($this->kasir)->patch(route('orders.items', $order), [
            'items' => [['service_id' => $this->reguler->id, 'qty' => '5']],
        ])->assertSessionHasErrors('items');
    }

    public function test_free_delivery_min_total_is_applied_after_weighing(): void
    {
        Setting::set('free_delivery_min_total', 30000);
        $order = $this->createJemputOrder();
        $this->assertSame(0, $order->delivery_discount); // total cucian belum diketahui

        $this->actingAs($this->kasir)->patch(route('orders.items', $order), [
            'items' => [['service_id' => $this->reguler->id, 'qty' => '5']],   // 35.000
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(10000, $order->delivery_discount);
        $this->assertSame(35000, $order->total);
    }

    public function test_weigh_later_not_allowed_without_pickup(): void
    {
        foreach (['none', 'antar'] as $mode) {
            $this->actingAs($this->kasir)->post(route('orders.store'), [
                'customer_name' => 'Nia', 'customer_phone' => '081313148938',
                'delivery_mode' => $mode, 'weigh_later' => '1',
            ])->assertSessionHasErrors();
        }
        $this->assertSame(0, Order::count());
    }

    public function test_jemput_saja_order_and_link_jemput_schedule(): void
    {
        $order = $this->createJemputOrder('jemput');
        $this->assertSame(5000, $order->delivery_fee);

        $this->actingAs($this->kasir)->post(route('deliveries.store'), [
            'type' => 'jemput', 'target' => 'customer', 'order_id' => $order->id,
            'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();

        $this->assertSame($order->id, Delivery::first()->order_id);
        $this->assertSame($order->customer_id, Delivery::first()->customer_id);
    }

    public function test_mitra_inputs_own_titipan_and_cannot_see_others(): void
    {
        $mitra = Mitra::create(['name' => 'Warung A', 'commission_type' => 'per_kg', 'commission_value' => 1000]);
        $other = Mitra::create(['name' => 'Warung B']);
        $user = User::create(['name' => 'Bu Sri', 'email' => 'm@test.id', 'password' => 'rahasia123', 'role' => 'mitra', 'mitra_id' => $mitra->id]);

        $this->actingAs($user)->get(route('mitra.orders.create'))->assertOk()->assertSee('Cuci Gosok Reguler');

        $this->actingAs($user)->post(route('mitra.orders.store'), [
            'customer_name' => 'Pak Joko',
            'customer_phone' => '0812-3456-7890',
            'items' => [['service_id' => $this->reguler->id, 'qty' => '4']],
            'mitra_id' => $other->id,          // diabaikan
            'discount' => 5000,                // diabaikan
            'pay_now' => '1',                  // diabaikan
        ])->assertSessionHasNoErrors()->assertRedirect(route('mitra.portal'));

        $order = Order::latest('id')->first();
        $this->assertSame($mitra->id, $order->mitra_id);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(28000, $order->total);
        $this->assertSame(0, $order->discount);
        $this->assertSame('belum', $order->payment_status);
        $this->assertSame('diterima', $order->status);
        $this->assertSame(4000, $order->commission_amount);  // 4 kg x Rp1.000
        $this->assertSame('081234567890', $order->customer->phone);

        $this->actingAs($user)->get(route('mitra.portal'))->assertOk()->assertSee($order->code);
        $this->actingAs($user)->get(route('mitra.orders.receipt', $order))->assertOk()->assertSee('Pak Joko');

        // Nota order mitra lain tidak bisa dibuka
        $foreign = Order::create(['code' => 'HS-LAIN', 'customer_id' => $order->customer_id, 'mitra_id' => $other->id]);
        $this->actingAs($user)->get(route('mitra.orders.receipt', $foreign))->assertNotFound();

        // Mitra tetap tidak bisa membuka menu kasir
        $this->actingAs($user)->get(route('orders.create'))->assertForbidden();
        $this->actingAs($this->kasir)->get(route('mitra.orders.create'))->assertForbidden();
    }

    public function test_weighing_completes_pickup_with_optional_courier(): void
    {
        Setting::set('staff_rate_jemput', 1000);
        $kurir = \App\Models\Staff::create(['name' => 'Andi', 'position' => 'kurir']);

        // 1) Belum ada jadwal jemput -> dibuat otomatis berstatus selesai, komisi ke kurir
        $order = $this->createJemputOrder('jemput');
        $this->actingAs($this->kasir)->patch(route('orders.items', $order), [
            'items' => [['service_id' => $this->reguler->id, 'qty' => '4']],
            'pickup_staff_id' => $kurir->id,
        ])->assertSessionHasNoErrors();

        $d = Delivery::where('order_id', $order->id)->where('type', 'jemput')->first();
        $this->assertNotNull($d);
        $this->assertSame('selesai', $d->status);
        $this->assertSame($kurir->id, $d->staff_id);
        $this->assertSame(1000, $kurir->unpaidCommission());

        // 2) Jadwal jemput sudah ada -> ditandai selesai, petugas boleh kosong
        $order2 = $this->createJemputOrder('keduanya');
        $this->actingAs($this->kasir)->post(route('deliveries.store'), [
            'type' => 'jemput', 'target' => 'customer', 'order_id' => $order2->id,
            'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->kasir)->get(route('orders.show', $order2))->assertOk()->assertSee('Petugas yang menjemput');
        $this->actingAs($this->kasir)->patch(route('orders.items', $order2), [
            'items' => [['service_id' => $this->reguler->id, 'qty' => '3']],
            'pickup_staff_id' => '',
        ])->assertSessionHasNoErrors();

        $jemput = Delivery::where('order_id', $order2->id)->where('type', 'jemput')->get();
        $this->assertCount(1, $jemput);                      // tidak dobel
        $this->assertSame('selesai', $jemput->first()->status);
        $this->assertNull($jemput->first()->staff_id);
        $this->assertSame(1000, $kurir->fresh()->unpaidCommission()); // tidak bertambah
    }
}
