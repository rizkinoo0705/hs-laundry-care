<?php

namespace Database\Seeders;

use App\Models\Mitra;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data awal. Aman dijalankan berulang (tidak membuat data ganda).
 * GANTI password akun-akun ini setelah login pertama.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Harga contoh. Ubah lewat menu Layanan sesuai harga sebenarnya.
        $services = [
            ['name' => 'Cuci Gosok Reguler', 'category' => 'kiloan', 'unit' => 'kg', 'price' => 7000, 'min_qty' => 3, 'duration_hours' => 48],
            ['name' => 'Gosok Saja', 'category' => 'gosok', 'unit' => 'kg', 'price' => 4000, 'min_qty' => 3, 'duration_hours' => 24],
            ['name' => 'Bed Cover', 'category' => 'premium', 'unit' => 'pcs', 'price' => 25000, 'duration_hours' => 72],
            ['name' => 'Jas / Blazer', 'category' => 'premium', 'unit' => 'pcs', 'price' => 30000, 'duration_hours' => 72],
            ['name' => 'Selimut', 'category' => 'satuan', 'unit' => 'pcs', 'price' => 20000, 'duration_hours' => 72],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(['name' => $service['name']], $service + ['active' => true]);
        }

        User::firstOrCreate(['email' => 'admin@hslaundry.test'], [
            'name' => 'Admin HS',
            'password' => 'password',
            'role' => 'admin',
        ]);

        User::firstOrCreate(['email' => 'kasir@hslaundry.test'], [
            'name' => 'Kasir HS',
            'password' => 'password',
            'role' => 'kasir',
        ]);

        $mitra = Mitra::firstOrCreate(['name' => 'Mitra Contoh'], [
            'owner_name' => 'Pemilik Warung',
            'phone' => '081200000000',
            'address' => 'Alamat mitra contoh',
            'commission_type' => config('laundry.default_commission_type'),
            'commission_value' => config('laundry.default_commission_value'),
        ]);

        User::firstOrCreate(['email' => 'mitra@hslaundry.test'], [
            'name' => 'Mitra Contoh',
            'password' => 'password',
            'role' => 'mitra',
            'mitra_id' => $mitra->id,
        ]);
    }
}
