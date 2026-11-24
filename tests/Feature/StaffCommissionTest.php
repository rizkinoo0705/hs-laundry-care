<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffCommission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $budi;
    private Staff $kurir;
    private Service $reguler;
    private Service $express;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@test.id', 'password' => 'rahasia123', 'role' => 'admin']);
        $this->budi = Staff::create(['name' => 'Budi', 'position' => 'produksi']);
        $this->kurir = Staff::create(['name' => 'Andi', 'position' => 'kurir']);
        $this->reguler = Service::create(['name' => 'Cuci Gosok Reguler', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 7000, 'min_qty' => 3, 'staff_commission' => 500]);
        $this->express = Service::create(['name' => 'Cuci Gosok Express', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 12000, 'staff_commission' => 1000]);
    }

    private function makeOrder(array $items, ?int $staffId): Order
    {
        $this->actingAs($this->admin)->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '081313148938',
            'delivery_mode' => 'none',
            'staff_id' => $staffId,
            'items' => $items,
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    public function test_commission_recorded_when_order_done_and_paid(): void
    {
        $order = $this->makeOrder([
            ['service_id' => $this->reguler->id, 'qty' => '2.5'],  // ditagih min. 3 kg -> 3 x 500
            ['service_id' => $this->express->id, 'qty' => '2'],    // 2 x 1000
        ], $this->budi->id);

        $this->assertSame($this->budi->id, $order->staff_id);

        // Selesai tapi belum lunas -> belum ada komisi
        $this->actingAs($this->admin)->patch(route('orders.status', $order), ['status' => 'selesai']);
        $this->assertSame(0, StaffCommission::count());

        // Lunas -> komisi tercatat, sekali saja
        $this->actingAs($this->admin)->patch(route('orders.pay', $order), ['payment_method' => 'tunai']);
        $this->actingAs($this->admin)->patch(route('orders.pay', $order), ['payment_method' => 'tunai']);

        $this->assertSame(2, StaffCommission::count());
        $this->assertSame(1500 + 2000, $this->budi->unpaidCommission());

        // Mengubah tarif tidak mengubah komisi yang sudah tercatat
        $this->reguler->update(['staff_commission' => 9999]);
        $this->assertSame(3500, $this->budi->fresh()->unpaidCommission());

        $this->actingAs($this->admin)->get(route('orders.show', $order))->assertOk()->assertSee('Budi');
    }

    public function test_paid_first_then_done_also_records(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), [
            'customer_name' => 'Nia', 'customer_phone' => '081313148938', 'delivery_mode' => 'none',
            'staff_id' => $this->budi->id, 'pay_now' => '1', 'payment_method' => 'tunai',
            'items' => [['service_id' => $this->express->id, 'qty' => '4']],
        ]);
        $order = Order::latest('id')->first();
        $this->assertSame(0, StaffCommission::count());

        $this->actingAs($this->admin)->patch(route('orders.status', $order), ['status' => 'selesai']);
        $this->assertSame(4000, $this->budi->unpaidCommission());
    }

    public function test_no_staff_no_commission_and_staff_can_be_changed_before_done(): void
    {
        $order = $this->makeOrder([['service_id' => $this->express->id, 'qty' => '1']], null);

        $this->actingAs($this->admin)->patch(route('orders.staff', $order), ['staff_id' => $this->budi->id])->assertSessionHasNoErrors();
        $this->assertSame($this->budi->id, $order->fresh()->staff_id);

        $this->actingAs($this->admin)->patch(route('orders.staff', $order), ['staff_id' => '']);
        $this->actingAs($this->admin)->patch(route('orders.pay', $order), ['payment_method' => 'tunai']);
        $this->actingAs($this->admin)->patch(route('orders.status', $order), ['status' => 'selesai']);
        $this->assertSame(0, StaffCommission::count());

        $this->actingAs($this->admin)->patch(route('orders.staff', $order), ['staff_id' => $this->budi->id])->assertSessionHasErrors('staff_id');
    }

    public function test_courier_gets_commission_per_trip_when_delivery_done(): void
    {
        $this->actingAs($this->admin)->put(route('staff.rates'), ['staff_rate_jemput' => 1000, 'staff_rate_antar' => 1500])->assertSessionHasNoErrors();

        $customer = Customer::create(['name' => 'Nia', 'phone' => '081313148938', 'address' => 'Jl. Melati 1']);
        $jemput = Delivery::create(['type' => 'jemput', 'customer_id' => $customer->id, 'address' => 'Jl. Melati 1', 'scheduled_at' => now()]);
        $antar = Delivery::create(['type' => 'antar', 'customer_id' => $customer->id, 'address' => 'Jl. Melati 1', 'scheduled_at' => now()]);

        // Tanpa kurir tidak boleh ditandai selesai (karena ada tarif komisi)
        $this->actingAs($this->admin)->patch(route('deliveries.status', $jemput), ['status' => 'selesai'])->assertSessionHasErrors('staff_id');

        $this->actingAs($this->admin)->patch(route('deliveries.status', $jemput), ['status' => 'selesai', 'staff_id' => $this->kurir->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('deliveries.status', $antar), ['status' => 'selesai', 'staff_id' => $this->kurir->id]);
        $this->assertSame(2500, $this->kurir->unpaidCommission());

        // Dibatalkan sebelum dibayar -> komisinya dihapus
        $this->actingAs($this->admin)->patch(route('deliveries.status', $antar), ['status' => 'batal']);
        $this->assertSame(1000, $this->kurir->fresh()->unpaidCommission());

        $this->actingAs($this->admin)->get(route('deliveries.index'))->assertOk()->assertSee('Andi');
    }

    public function test_payout_marks_commissions_and_pages_render(): void
    {
        $order = $this->makeOrder([['service_id' => $this->express->id, 'qty' => '3']], $this->budi->id);
        $this->actingAs($this->admin)->patch(route('orders.pay', $order), ['payment_method' => 'tunai']);
        $this->actingAs($this->admin)->patch(route('orders.status', $order), ['status' => 'selesai']);

        $this->actingAs($this->admin)->get(route('staff.index'))->assertOk()->assertSee('Budi');
        $this->actingAs($this->admin)->get(route('staff.show', $this->budi))->assertOk()->assertSee('Rp3.000');
        $this->actingAs($this->admin)->get(route('reports.index'))->assertOk()->assertSee('Komisi petugas');

        $this->actingAs($this->admin)->post(route('staff.payout', $this->budi), ['notes' => 'tunai'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->budi->unpaidCommission());
        $this->assertSame(3000, $this->budi->payouts()->first()->amount);

        $this->actingAs($this->admin)->post(route('staff.payout', $this->budi))->assertSessionHasErrors('payout');
    }

    public function test_admin_manages_staff_and_kasir_cannot(): void
    {
        $this->actingAs($this->admin)->post(route('staff.store'), ['name' => 'Sari', 'position' => 'produksi', 'active' => '1'])
            ->assertSessionHasNoErrors();
        $sari = Staff::where('name', 'Sari')->first();
        $this->actingAs($this->admin)->put(route('staff.update', $sari), ['name' => 'Sari W', 'position' => 'umum'])->assertSessionHasNoErrors();
        $this->assertFalse($sari->fresh()->active);
        $this->actingAs($this->admin)->get(route('staff.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('staff.edit', $sari))->assertOk();

        $kasir = User::create(['name' => 'Kasir', 'email' => 'k@test.id', 'password' => 'rahasia123', 'role' => 'kasir']);
        $this->actingAs($kasir)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('orders.create'))->assertOk()->assertSee('Petugas pengerjaan');
    }
}
