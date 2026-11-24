<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private Service $kiloan;
    private Service $bedcover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::create(['name' => 'Kasir', 'email' => 'k@test.id', 'password' => 'rahasia123', 'role' => 'kasir']);
        $this->kiloan = Service::create(['name' => 'Cuci Gosok', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 7000, 'duration_hours' => 48]);
        $this->bedcover = Service::create(['name' => 'Bed Cover', 'category' => 'premium', 'unit' => 'pcs', 'price' => 25000, 'duration_hours' => 72]);
    }

    public function test_kasir_creates_order_with_new_customer_and_mitra_commission(): void
    {
        $mitra = Mitra::create(['name' => 'Warung A', 'commission_type' => 'per_kg', 'commission_value' => 1000]);

        $response = $this->actingAs($this->kasir)->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '+62 813-1314-8938',
            'delivery_method' => 'ambil',
            'mitra_id' => $mitra->id,
            'items' => [
                ['service_id' => $this->kiloan->id, 'qty' => '3.5'],
                ['service_id' => $this->bedcover->id, 'qty' => '1'],
            ],
            'discount' => 500,
        ]);

        $order = Order::first();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame('081313148938', $order->customer->phone);
        $this->assertSame(24500 + 25000, $order->subtotal);
        $this->assertSame(49000, $order->total);
        $this->assertEquals(3.5, (float) $order->total_kg);
        $this->assertSame(3500, $order->commission_amount); // 3,5 kg x Rp1.000
        $this->assertSame('belum', $order->payment_status);
        $this->assertStringStartsWith('HS'.now()->format('ymd').'-001', $order->code);
        $this->assertCount(2, $order->items);

        $this->actingAs($this->kasir)->get(route('orders.show', $order))->assertOk()->assertSee($order->code);
        $this->actingAs($this->kasir)->get(route('orders.receipt', $order))->assertOk()->assertSee('BELUM BAYAR');
    }

    public function test_minimum_weight_is_charged_when_below_minimum(): void
    {
        $this->kiloan->update(['min_qty' => 3]);
        $mitra = Mitra::create(['name' => 'Warung A', 'commission_type' => 'per_kg', 'commission_value' => 1000]);

        // 2,5 kg -> ditagih 3 kg
        $this->actingAs($this->kasir)->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '081313148938',
            'delivery_method' => 'ambil',
            'mitra_id' => $mitra->id,
            'items' => [['service_id' => $this->kiloan->id, 'qty' => '2.5']],
        ])->assertSessionHasNoErrors();

        $order = Order::latest('id')->first();
        $item = $order->items->first();
        $this->assertSame(21000, $order->total);              // 3 x 7.000
        $this->assertEquals(2.5, (float) $order->total_kg);   // berat asli tetap tercatat
        $this->assertEquals(3.0, (float) $item->charged_qty);
        $this->assertTrue($item->usesMinimum());
        $this->assertSame(3000, $order->commission_amount);  // komisi dari berat yang ditagih

        $this->actingAs($this->kasir)->get(route('orders.receipt', $order))->assertOk()->assertSee('min. 3kg');

        // 4 kg -> ditagih 4 kg (di atas minimum)
        $this->actingAs($this->kasir)->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '081313148938',
            'delivery_method' => 'ambil',
            'items' => [['service_id' => $this->kiloan->id, 'qty' => '4']],
        ]);

        $order = Order::latest('id')->first();
        $this->assertSame(28000, $order->total);
        $this->assertFalse($order->items->first()->usesMinimum());
    }

    public function test_admin_can_set_minimum_on_service(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.id', 'password' => 'rahasia123', 'role' => 'admin']);

        $this->actingAs($admin)->put(route('services.update', $this->kiloan), [
            'name' => 'Cuci Gosok', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 7000,
            'min_qty' => '3', 'duration_hours' => 48, 'active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertEquals(3.0, (float) $this->kiloan->fresh()->min_qty);

        // Dikosongkan -> tanpa minimum
        $this->actingAs($admin)->put(route('services.update', $this->kiloan), [
            'name' => 'Cuci Gosok', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 7000,
            'min_qty' => '', 'duration_hours' => 48, 'active' => '1',
        ]);
        $this->assertNull($this->kiloan->fresh()->min_qty);

        $this->actingAs($admin)->get(route('services.edit', $this->kiloan))->assertOk();
        $this->actingAs($this->kasir)->get(route('orders.create'))->assertOk();
    }

    public function test_percent_commission(): void
    {
        $mitra = Mitra::create(['name' => 'Toko B', 'commission_type' => 'percent', 'commission_value' => 10]);

        $this->assertSame(2450, $mitra->commissionFor(3.5, 24500));
    }

    public function test_existing_customer_is_reused_by_phone(): void
    {
        $customer = Customer::create(['name' => 'Nia', 'phone' => '081313148938']);

        $this->actingAs($this->kasir)->post(route('orders.store'), [
            'customer_name' => 'Nama Lain',
            'customer_phone' => '0813 1314 8938',
            'delivery_method' => 'ambil',
            'items' => [['service_id' => $this->kiloan->id, 'qty' => '2']],
        ]);

        $this->assertSame(1, Customer::count());
        $this->assertSame($customer->id, Order::first()->customer_id);
    }

    public function test_member_gets_stamp_when_order_done_and_paid_then_redeems(): void
    {
        config(['laundry.stamp_target' => 2]);
        $customer = Customer::create(['name' => 'Nia', 'phone' => '081313148938', 'is_member' => true]);
        $this->assertSame('HS-M'.str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT), $customer->fresh()->member_code);

        foreach ([1, 2] as $n) {
            $this->actingAs($this->kasir)->post(route('orders.store'), [
                'customer_name' => 'Nia',
                'customer_phone' => '081313148938',
                'delivery_method' => 'ambil',
                'items' => [['service_id' => $this->kiloan->id, 'qty' => '2']],
            ]);
            $order = Order::latest('id')->first();

            // Selesai dulu, belum lunas -> belum dapat stempel
            $this->actingAs($this->kasir)->patch(route('orders.status', $order), ['status' => 'selesai']);
            $this->assertSame($n - 1, $customer->fresh()->stamps);

            // Lunas -> dapat stempel, hanya sekali
            $this->actingAs($this->kasir)->patch(route('orders.pay', $order), ['payment_method' => 'tunai']);
            $this->actingAs($this->kasir)->patch(route('orders.pay', $order), ['payment_method' => 'tunai']);
            $this->assertSame($n, $customer->fresh()->stamps);
        }

        $this->actingAs($this->kasir)->post(route('customers.redeem', $customer))->assertSessionHasNoErrors();
        $this->assertSame(0, $customer->fresh()->stamps);
        $this->assertSame(3, $customer->stampTransactions()->count());
    }

    public function test_status_cannot_go_backwards(): void
    {
        $this->actingAs($this->kasir)->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '081313148938',
            'delivery_method' => 'ambil',
            'items' => [['service_id' => $this->kiloan->id, 'qty' => '1']],
        ]);
        $order = Order::first();

        $this->actingAs($this->kasir)->patch(route('orders.status', $order), ['status' => 'siap'])->assertSessionHasNoErrors();
        $this->actingAs($this->kasir)->patch(route('orders.status', $order), ['status' => 'dicuci'])->assertSessionHasErrors('status');
        $this->assertSame('siap', $order->fresh()->status);
    }
}
