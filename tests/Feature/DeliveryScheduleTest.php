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

    public function test_antar_requires_order_and_takes_customer_from_order(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.id', 'password' => 'rahasia123', 'role' => 'admin']);
        $nia = Customer::create(['name' => 'Nia', 'phone' => '081313148938', 'address' => 'Jl. Melati 1']);
        $budi = Customer::create(['name' => 'Budi', 'phone' => '081200000002', 'address' => 'Jl. Mawar 2']);
        $order = Order::create(['code' => 'HS-T-001', 'customer_id' => $nia->id, 'status' => 'siap', 'subtotal' => 21000, 'total' => 21000]);

        // Antar tanpa order -> ditolak
        $this->actingAs($admin)->post(route('deliveries.store'), [
            'type' => 'antar', 'target' => 'customer', 'customer_id' => $nia->id, 'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('order_id');
        $this->assertSame(0, Delivery::count());

        // Antar dengan order -> pelanggan diambil dari order, walau yang dipilih berbeda/kosong
        $this->actingAs($admin)->post(route('deliveries.store'), [
            'type' => 'antar', 'target' => 'customer', 'order_id' => $order->id, 'customer_id' => $budi->id,
            'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('deliveries.store'), [
            'type' => 'antar', 'target' => 'customer', 'order_id' => $order->id,
            'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();

        $this->assertSame([$nia->id, $nia->id], Delivery::orderBy('id')->pluck('customer_id')->all());
        $this->assertSame('Jl. Melati 1', Delivery::first()->address);

        // Jemput tetap boleh tanpa order
        $this->actingAs($admin)->post(route('deliveries.store'), [
            'type' => 'jemput', 'target' => 'customer', 'customer_id' => $budi->id, 'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, Delivery::count());

        $this->actingAs($admin)->get(route('deliveries.create'))->assertOk()->assertSee('HS-T-001');
        $this->actingAs($admin)->get(route('deliveries.index', ['date' => now()->addHour()->toDateString()]))->assertOk()->assertSee('HS-T-001');
    }
}
