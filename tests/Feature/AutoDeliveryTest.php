<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private Service $reguler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::create(['name' => 'Kasir', 'email' => 'k@test.id', 'password' => 'rahasia123', 'role' => 'kasir']);
        $this->reguler = Service::create(['name' => 'Cuci Gosok Reguler', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 7000]);
        Customer::create(['name' => 'Nia', 'phone' => '081313148938', 'address' => 'Jl. Melati 1']);
    }

    private function order(string $mode): Order
    {
        $this->actingAs($this->kasir)->post(route('orders.store'), [
            'customer_name' => 'Nia', 'customer_phone' => '081313148938', 'delivery_mode' => $mode,
            'manual_delivery_fee' => $mode === 'none' ? null : 5000,
            'items' => [['service_id' => $this->reguler->id, 'qty' => '3']],
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    private function setStatus(Order $order, string $status): void
    {
        $this->actingAs($this->kasir)->patch(route('orders.status', $order), ['status' => $status])->assertSessionHasNoErrors();
    }

    private function pay(Order $order): void
    {
        $this->actingAs($this->kasir)->patch(route('orders.pay', $order), ['payment_method' => 'tunai'])->assertSessionHasNoErrors();
    }

    public function test_siap_then_paid_creates_delivery_without_courier(): void
    {
        $order = $this->order('keduanya');

        $this->setStatus($order, 'siap');
        $this->assertSame(0, Delivery::count());             // belum lunas -> belum diantar

        $this->pay($order);
        $d = Delivery::first();
        $this->assertNotNull($d);
        $this->assertSame('antar', $d->type);
        $this->assertSame($order->id, $d->order_id);
        $this->assertNull($d->staff_id);
        $this->assertSame('Jl. Melati 1', $d->address);

        // Muncul di bagian "Perlu kurir"
        $this->actingAs($this->kasir)->get(route('deliveries.index'))->assertOk()->assertSee('Perlu kurir')->assertSee($order->code);
    }

    public function test_paid_then_siap_creates_delivery_once(): void
    {
        $order = $this->order('antar');
        $this->pay($order);
        $this->assertSame(0, Delivery::count());             // belum siap

        $this->setStatus($order, 'siap');
        $this->assertSame(1, Delivery::count());

        $this->pay($order);                                   // tidak dobel
        $this->assertSame(1, Delivery::count());
    }

    public function test_non_antar_orders_never_create_delivery(): void
    {
        foreach (['none', 'jemput'] as $mode) {
            $order = $this->order($mode);
            $this->pay($order);
            $this->setStatus($order, 'siap');
        }
        $this->assertSame(0, Delivery::count());
    }

    public function test_delivery_done_marks_order_selesai_and_records_commission(): void
    {
        $order = $this->order('antar');
        $this->setStatus($order, 'siap');
        $this->pay($order);
        $d = Delivery::first();
        $kurir = Staff::create(['name' => 'Andi', 'position' => 'kurir']);

        $this->actingAs($this->kasir)->patch(route('deliveries.status', $d), ['status' => 'jalan', 'staff_id' => $kurir->id])->assertSessionHasNoErrors();
        $this->assertSame('siap', $order->fresh()->status);

        $this->actingAs($this->kasir)->patch(route('deliveries.status', $d), ['status' => 'selesai'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);

        $this->actingAs($this->kasir)->get(route('deliveries.index'))->assertOk()->assertDontSee('Perlu kurir');
    }

    public function test_order_selesai_from_order_page_also_finishes_delivery(): void
    {
        \App\Models\Setting::set('staff_rate_antar', 1500);

        $order = $this->order('keduanya');
        $this->setStatus($order, 'siap');
        $this->pay($order);
        $d = Delivery::first();
        $kurir = Staff::create(['name' => 'Andi', 'position' => 'kurir']);
        $d->update(['staff_id' => $kurir->id]);

        // Diselesaikan dari halaman order -> jadwal antar ikut selesai, komisi kurir tercatat
        $this->setStatus($order->fresh(), 'selesai');
        $d->refresh();
        $this->assertSame('selesai', $d->status);
        $this->assertNotNull($d->completed_at);
        $this->assertSame(1500, $kurir->unpaidCommission());

        $this->actingAs($this->kasir)->get(route('deliveries.index'))->assertDontSee('Perlu kurir');
    }

    public function test_order_batal_cancels_active_deliveries(): void
    {
        $order = $this->order('antar');
        $this->setStatus($order, 'siap');
        $this->pay($order);
        $this->assertSame('terjadwal', Delivery::first()->status);

        $this->setStatus($order->fresh(), 'batal');
        $this->assertSame('batal', Delivery::first()->status);
    }
}
