<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Service $kiloan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@test.id', 'password' => 'rahasia123', 'role' => 'admin']);
        $this->kiloan = Service::create(['name' => 'Cuci Gosok', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 7000]);

        // Outlet di Monas, Jakarta
        Setting::set('outlet_lat', '-6.1753924');
        Setting::set('outlet_lng', '106.8271528');

        DeliveryZone::create(['name' => 'Dekat', 'max_km' => 2, 'fee' => 5000]);
        DeliveryZone::create(['name' => 'Sedang', 'max_km' => 5, 'fee' => 10000]);
    }

    private function customerAt(float $km, array $extra = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Nia', 'phone' => '081313148938',
            'latitude' => -6.18, 'longitude' => 106.83,
            'distance_km' => $km, 'distance_source' => 'jalan',
        ], $extra));
    }

    public function test_zone_is_picked_by_distance_and_charged_per_trip(): void
    {
        $fees = app(DeliveryFeeService::class);

        $c = $this->customerAt(1.5);
        $q = $fees->quote($c, 2, 21000);
        $this->assertSame('Dekat', $q['zone_name']);
        $this->assertSame(10000, $q['fee']); // 2 perjalanan x 5.000
        $this->assertSame(10000, $q['total']);

        $q = $fees->quote((clone $c)->fill(['distance_km' => 3.2]), 1, 21000);
        $this->assertSame('Sedang', $q['zone_name']);
        $this->assertSame(10000, $q['fee']);

        $q = $fees->quote((clone $c)->fill(['distance_km' => 9]), 1, 21000);
        $this->assertTrue($q['out_of_zone']);
        $this->assertSame(0, $q['fee']);

        $q = $fees->quote((clone $c)->fill(['distance_km' => 9]), 1, 21000, 20000);
        $this->assertTrue($q['manual']);
        $this->assertSame(20000, $q['total']);
    }

    public function test_customer_without_location_needs_manual_fee(): void
    {
        $customer = Customer::create(['name' => 'Baru', 'phone' => '081200000009']);
        $q = app(DeliveryFeeService::class)->quote($customer, 1, 10000);

        $this->assertTrue($q['no_location']);
        $this->assertSame(0, $q['total']);
    }

    public function test_straight_line_distance_used_when_no_road_distance(): void
    {
        // Titik ~1,1 km dari outlet, tanpa distance_km tersimpan
        $customer = Customer::create(['name' => 'X', 'phone' => '081200000010', 'latitude' => -6.1853924, 'longitude' => 106.8271528]);
        $q = app(DeliveryFeeService::class)->quote($customer, 1, 0);

        $this->assertEqualsWithDelta(1.11, $q['distance_km'], 0.05);
        $this->assertSame('Dekat', $q['zone_name']);
    }

    public function test_promos_free_percent_member_and_min_total(): void
    {
        $fees = app(DeliveryFeeService::class);

        $c = $this->customerAt(1.5, ['delivery_promo' => 'percent', 'delivery_promo_value' => 50]);
        $q = $fees->quote($c, 2, 21000);
        $this->assertSame(5000, $q['discount']);
        $this->assertSame(5000, $q['total']);

        $c->update(['delivery_promo' => 'free']);
        $q = $fees->quote($c, 2, 21000);
        $this->assertSame(0, $q['total']);

        // Promo kedaluwarsa tidak berlaku
        $c->update(['delivery_promo_until' => now()->subDay()->toDateString()]);
        $q = $fees->quote($c->fresh(), 2, 21000);
        $this->assertSame(10000, $q['total']);

        // Gratis ongkir minimal belanja
        Setting::set('free_delivery_min_total', 50000);
        $this->assertSame(10000, $fees->quote($c->fresh(), 2, 49000)['total']);
        $this->assertSame(0, $fees->quote($c->fresh(), 2, 50000)['total']);

        // Gratis ongkir member
        Setting::set('free_delivery_min_total', 0);
        Setting::set('free_delivery_members', '1');
        $c->update(['is_member' => true]);
        $q = $fees->quote($c->fresh(), 2, 1000);
        $this->assertSame(0, $q['total']);
        $this->assertSame('Gratis ongkir member', $q['note']);
    }

    public function test_order_total_includes_ongkir_but_commission_does_not(): void
    {
        $this->customerAt(1.5);
        $mitra = Mitra::create(['name' => 'Warung', 'commission_type' => 'percent', 'commission_value' => 10]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '081313148938',
            'pickup' => '1',
            'delivery_method' => 'antar',
            'mitra_id' => $mitra->id,
            'items' => [['service_id' => $this->kiloan->id, 'qty' => '3']],
        ])->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertTrue($order->pickup);
        $this->assertSame(2, $order->delivery_trips);
        $this->assertSame(10000, $order->delivery_fee);
        $this->assertSame(21000 + 10000, $order->total);
        $this->assertSame(2100, $order->commission_amount); // 10% dari 21.000, tanpa ongkir

        $this->actingAs($this->admin)->get(route('orders.show', $order))->assertOk()->assertSee('Ongkir');
        $this->actingAs($this->admin)->get(route('orders.receipt', $order))->assertOk()->assertSee('Ongkir');
        $this->actingAs($this->admin)->get(route('reports.index'))->assertOk()->assertSee('Ongkir antar-jemput');
    }

    public function test_delivery_mode_choice_maps_to_trips(): void
    {
        $this->customerAt(1.5);
        $expected = ['none' => [false, 'ambil', 0, 0], 'jemput' => [true, 'ambil', 1, 5000], 'antar' => [false, 'antar', 1, 5000], 'keduanya' => [true, 'antar', 2, 10000]];

        foreach ($expected as $mode => [$pickup, $method, $trips, $fee]) {
            $this->actingAs($this->admin)->post(route('orders.store'), [
                'customer_name' => 'Nia',
                'customer_phone' => '081313148938',
                'delivery_mode' => $mode,
                'items' => [['service_id' => $this->kiloan->id, 'qty' => '3']],
            ])->assertSessionHasNoErrors();

            $order = Order::latest('id')->first();
            $this->assertSame($pickup, $order->pickup, $mode);
            $this->assertSame($method, $order->delivery_method, $mode);
            $this->assertSame($trips, $order->delivery_trips, $mode);
            $this->assertSame(21000 + $fee, $order->total, $mode);
        }

        $this->actingAs($this->admin)->get(route('orders.create'))->assertOk()->assertSee('Jemput + antar');
    }

    public function test_quote_endpoint_returns_json(): void
    {
        $this->customerAt(1.5);

        $this->actingAs($this->admin)
            ->getJson(route('orders.quote', ['phone' => '0813-1314-8938', 'pickup' => 1, 'delivery_method' => 'ambil', 'laundry_total' => 21000]))
            ->assertOk()
            ->assertJson(['trips' => 1, 'zone_name' => 'Dekat', 'fee' => 5000, 'total' => 5000, 'customer_found' => true]);
    }

    public function test_settings_page_saves_outlet_zones_and_promos(): void
    {
        $this->actingAs($this->admin)->get(route('settings.delivery'))->assertOk()->assertSee('Zona tarif');

        $dekat = DeliveryZone::where('name', 'Dekat')->first();
        $sedang = DeliveryZone::where('name', 'Sedang')->first();

        $this->actingAs($this->admin)->put(route('settings.delivery.update'), [
            'outlet_lat' => '-6.2000000',
            'outlet_lng' => '106.8166660',
            'free_delivery_min_total' => '75000',
            'free_delivery_members' => '1',
            'zones' => [
                ['id' => $dekat->id, 'name' => 'Dekat', 'max_km' => '3', 'fee' => '6000', 'active' => '1'],
                ['id' => $sedang->id, 'delete' => '1'],
                ['id' => '', 'name' => 'Jauh', 'max_km' => '8', 'fee' => '15000', 'active' => '1'],
                ['id' => '', 'name' => '', 'max_km' => '', 'fee' => ''],
            ],
        ])->assertRedirect(route('settings.delivery'))->assertSessionHasNoErrors();

        Setting::flushCache();
        $this->assertSame([-6.2, 106.816666], Setting::outletLocation());
        $this->assertSame('75000', Setting::get('free_delivery_min_total'));
        $this->assertSame(['Dekat', 'Jauh'], DeliveryZone::orderBy('max_km')->pluck('name')->all());
        $this->assertSame(6000, $dekat->fresh()->fee);
    }

    public function test_customer_form_saves_location_road_distance_and_promo(): void
    {
        $this->actingAs($this->admin)->post(route('customers.store'), [
            'name' => 'Budi',
            'phone' => '081211112222',
            'latitude' => '-6.1853924',
            'longitude' => '106.8271528',
            'road_distance_km' => '1.6',   // jarak jalan dari peta
            'delivery_promo' => 'percent',
            'delivery_promo_value' => '25',
        ])->assertSessionHasNoErrors();

        $c = Customer::where('phone', '081211112222')->first();
        $this->assertEquals(1.6, (float) $c->distance_km);
        $this->assertSame('jalan', $c->distance_source);
        $this->assertSame('Diskon ongkir 25%', $c->deliveryPromoLabel());

        // Jarak jalan yang tidak masuk akal diganti jarak garis lurus
        $this->actingAs($this->admin)->put(route('customers.update', $c), [
            'name' => 'Budi', 'phone' => '081211112222',
            'latitude' => '-6.1853924', 'longitude' => '106.8271528',
            'road_distance_km' => '0.1',
        ])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertSame('lurus', $c->distance_source);
        $this->assertEqualsWithDelta(1.11, (float) $c->distance_km, 0.05);

        $this->actingAs($this->admin)->get(route('customers.show', $c))->assertOk()->assertSee('Dekat');
        $this->actingAs($this->admin)->get(route('customers.edit', $c))->assertOk()->assertSee('customer-map', false);
    }
}
