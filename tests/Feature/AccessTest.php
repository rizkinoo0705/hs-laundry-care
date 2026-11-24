<?php

namespace Tests\Feature;

use App\Models\Mitra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_user_can_login_and_see_dashboard(): void
    {
        User::create(['name' => 'Admin', 'email' => 'admin@test.id', 'password' => 'rahasia123', 'role' => 'admin']);

        $this->post('/login', ['email' => 'admin@test.id', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));

        $this->get('/')->assertOk()->assertSee('Dashboard');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create(['name' => 'Lama', 'email' => 'lama@test.id', 'password' => 'rahasia123', 'role' => 'kasir', 'active' => false]);

        $this->post('/login', ['email' => 'lama@test.id', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_kasir_cannot_open_admin_pages(): void
    {
        $kasir = User::create(['name' => 'Kasir', 'email' => 'k@test.id', 'password' => 'rahasia123', 'role' => 'kasir']);

        $this->actingAs($kasir)->get(route('orders.create'))->assertOk();
        $this->actingAs($kasir)->get(route('services.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('mitras.index'))->assertForbidden();
    }

    public function test_mitra_is_sent_to_portal_and_cannot_open_orders(): void
    {
        $mitra = Mitra::create(['name' => 'Warung A']);
        $user = User::create(['name' => 'Mitra', 'email' => 'm@test.id', 'password' => 'rahasia123', 'role' => 'mitra', 'mitra_id' => $mitra->id]);

        $this->actingAs($user)->get('/')->assertRedirect(route('mitra.portal'));
        $this->actingAs($user)->get(route('mitra.portal'))->assertOk()->assertSee('Warung A');
        $this->actingAs($user)->get(route('orders.index'))->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.id', 'password' => 'rahasia123', 'role' => 'admin']);

        foreach (['dashboard', 'orders.index', 'orders.create', 'customers.index', 'customers.create',
                     'deliveries.index', 'deliveries.create', 'services.index', 'services.create',
                     'mitras.index', 'mitras.create', 'users.index', 'users.create', 'reports.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }
}
