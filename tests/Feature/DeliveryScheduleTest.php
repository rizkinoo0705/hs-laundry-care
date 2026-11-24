<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@test.id', 'password' => 'rahasia123', 'role' => 'admin']);
    }

    private function order(string $code, Customer $c, string $status, string $method, bool $pickup = false): Order
    {
        return Order::create(['code' => $code, 'customer_id' => $c->id, 'status' => $status, 'delivery_method' => $method,
            'pickup' => $pickup, 'subtotal' => 21000, 'total' => 21000]);
    }

    private function schedule(array $data)
    {
        return $this->actingAs($this->admin)->post(route('deliveries.store'), $data + [
            'target' => 'customer', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
        ]);
    }

    public function test_antar_requires_ready_antar_order_and_takes_customer_from_order(): void
    {
        $nia = Customer::create(['name' => 'Nia', 'phone' => '081313148938', 'address' => 'Jl. Melati 1']);
        $budi = Customer::create(['name' => 'Budi', 'phone' => '081200000002', 'address' => 'Jl. Mawar 2']);

        $ready = $this->order('HS-SIAP-ANTAR', $nia, 'siap', 'antar', true);       // jemput + antar, siap
        $ambil = $this->order('HS-SIAP-AMBIL', $budi, 'siap', 'ambil');            // diambil sendiri
        $proses = $this->order('HS-PROSES-ANTAR', $budi, 'dicuci', 'antar');       // belum siap
        $jemput = $this->order('HS-JEMPUT', $budi, 'diterima', 'ambil', true);     // perlu dijemput

        // Daftar order di form
        $page = $this->actingAs($this->admin)->get(route('deliveries.create'))->assertOk();
        $page->assertSee('HS-SIAP-ANTAR')->assertSee('HS-JEMPUT')
            ->assertDontSee('HS-SIAP-AMBIL')->assertDontSee('HS-PROSES-ANTAR');

        // Antar tanpa order / order tidak memenuhi syarat -> ditolak
        $this->schedule(['type' => 'antar', 'customer_id' => $nia->id])->assertSessionHasErrors('order_id');
        $this->schedule(['type' => 'antar', 'order_id' => $ambil->id])->assertSessionHasErrors('order_id');
        $this->schedule(['type' => 'antar', 'order_id' => $proses->id])->assertSessionHasErrors('order_id');
        $this->assertSame(0, Delivery::count());

        // Order siap + layanan antar -> berhasil, pelanggan dari order
        $this->schedule(['type' => 'antar', 'order_id' => $ready->id, 'customer_id' => $budi->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $d = Delivery::first();
        $this->assertSame($nia->id, $d->customer_id);
        $this->assertSame('Jl. Melati 1', $d->address);

        // Tidak boleh dobel, dan hilang dari daftar
        $this->schedule(['type' => 'antar', 'order_id' => $ready->id])->assertSessionHasErrors('order_id');
        $this->actingAs($this->admin)->get(route('deliveries.create'))->assertDontSee('HS-SIAP-ANTAR');

        // Jemput tetap boleh tanpa order
        $this->schedule(['type' => 'jemput', 'customer_id' => $budi->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, Delivery::count());

        $this->actingAs($this->admin)->get(route('deliveries.index', ['date' => now()->addHour()->toDateString()]))
            ->assertOk()->assertSee('HS-SIAP-ANTAR');
    }
}
