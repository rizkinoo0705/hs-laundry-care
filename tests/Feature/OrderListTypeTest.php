<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderListTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_list_shows_and_filters_service_type(): void
    {
        $kasir = User::create(['name' => 'Kasir', 'email' => 'k@test.id', 'password' => 'rahasia123', 'role' => 'kasir']);
        $c = Customer::create(['name' => 'Nia', 'phone' => '081313148938']);
        $mitra = Mitra::create(['name' => 'Warung Bu Sri']);

        $make = fn (string $code, array $attrs) => Order::create($attrs + ['code' => $code, 'customer_id' => $c->id]);
        $make('HS-KEDUANYA', ['pickup' => true, 'delivery_method' => 'antar']);
        $make('HS-JEMPUT', ['pickup' => true, 'delivery_method' => 'ambil']);
        $make('HS-ANTAR', ['pickup' => false, 'delivery_method' => 'antar']);
        $make('HS-SENDIRI', ['pickup' => false, 'delivery_method' => 'ambil']);
        $make('HS-DROPBOX', ['mitra_id' => $mitra->id]);

        $expected = [
            'HS-KEDUANYA' => 'keduanya', 'HS-JEMPUT' => 'jemput', 'HS-ANTAR' => 'antar',
            'HS-SENDIRI' => 'none', 'HS-DROPBOX' => 'dropbox',
        ];
        foreach ($expected as $code => $type) {
            $this->assertSame($type, Order::where('code', $code)->first()->serviceType(), $code);
        }

        $this->actingAs($kasir)->get(route('orders.index'))->assertOk()
            ->assertSee('Antar-jemput customer')->assertSee('Antar-jemput dropbox')
            ->assertSee('Jemput saja')->assertSee('Antar saja')->assertSee('Datang sendiri')
            ->assertSee('Warung Bu Sri');

        foreach ($expected as $code => $type) {
            $res = $this->actingAs($kasir)->get(route('orders.index', ['type' => $type]))->assertOk()->assertSee($code);
            foreach (array_keys($expected) as $other) {
                if ($other !== $code) {
                    $res->assertDontSee($other);
                }
            }
        }
    }
}
