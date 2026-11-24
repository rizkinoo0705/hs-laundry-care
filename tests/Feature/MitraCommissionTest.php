<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MitraCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Mitra $mitra, string $code, string $status, string $payment, int $commission): Order
    {
        $customer = Customer::firstOrCreate(['phone' => '081300000001'], ['name' => 'Pelanggan']);

        return Order::create([
            'code' => $code,
            'customer_id' => $customer->id,
            'mitra_id' => $mitra->id,
            'status' => $status,
            'payment_status' => $payment,
            'subtotal' => 14000,
            'total' => 14000,
            'total_kg' => 2,
            'commission_amount' => $commission,
        ]);
    }

    public function test_mitra_portal_shows_only_own_orders(): void
    {
        $a = Mitra::create(['name' => 'Warung A']);
        $b = Mitra::create(['name' => 'Warung B']);
        $this->makeOrder($a, 'HS-A-1', 'dicuci', 'belum', 2000);
        $this->makeOrder($b, 'HS-B-1', 'dicuci', 'belum', 2000);

        $user = User::create(['name' => 'A', 'email' => 'a@test.id', 'password' => 'rahasia123', 'role' => 'mitra', 'mitra_id' => $a->id]);

        $this->actingAs($user)->get(route('mitra.portal'))
            ->assertOk()
            ->assertSee('HS-A-1')
            ->assertDontSee('HS-B-1');
    }

    public function test_admin_pays_out_only_done_and_paid_orders(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.id', 'password' => 'rahasia123', 'role' => 'admin']);
        $mitra = Mitra::create(['name' => 'Warung A']);

        $ready1 = $this->makeOrder($mitra, 'HS-1', 'selesai', 'lunas', 2000);
        $ready2 = $this->makeOrder($mitra, 'HS-2', 'selesai', 'lunas', 3000);
        $notPaid = $this->makeOrder($mitra, 'HS-3', 'selesai', 'belum', 4000);
        $notDone = $this->makeOrder($mitra, 'HS-4', 'dicuci', 'lunas', 5000);

        $this->assertSame(5000, $mitra->unpaidCommission());

        $this->actingAs($admin)->post(route('mitras.payout', $mitra), ['notes' => 'transfer'])
            ->assertSessionHasNoErrors();

        $payout = $mitra->payouts()->first();
        $this->assertSame(5000, $payout->amount);
        $this->assertSame(2, $payout->orders_count);
        $this->assertSame($payout->id, $ready1->fresh()->mitra_payout_id);
        $this->assertSame($payout->id, $ready2->fresh()->mitra_payout_id);
        $this->assertNull($notPaid->fresh()->mitra_payout_id);
        $this->assertNull($notDone->fresh()->mitra_payout_id);
        $this->assertSame(0, $mitra->unpaidCommission());

        // Tidak ada lagi yang bisa dicairkan
        $this->actingAs($admin)->post(route('mitras.payout', $mitra))->assertSessionHasErrors('payout');

        $this->actingAs($admin)->get(route('mitras.show', $mitra))->assertOk();
        $this->actingAs($admin)->get(route('reports.index'))->assertOk()->assertSee('Warung A');
    }
}
