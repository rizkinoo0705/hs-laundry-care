<?php

return [
    'name' => 'HS Laundry & Care',
    'tagline' => 'Bersih, Wangi, Kami yang Urus',
    'wa_number' => env('LAUNDRY_WA', '0813-1314-8938'),
    'address' => env('LAUNDRY_ADDRESS', ''),

    // Program stempel member: setiap order member yang selesai & lunas dapat 1 stempel.
    'stamp_target' => (int) env('LAUNDRY_STAMP_TARGET', 10),
    'stamp_reward' => env('LAUNDRY_STAMP_REWARD', 'Hadiah member'),
    'stamp_min_total' => (int) env('LAUNDRY_STAMP_MIN_TOTAL', 0),

    // Komisi bawaan untuk mitra dropbox baru (bisa diubah per mitra).
    'default_commission_type' => 'per_kg',
    'default_commission_value' => 1000,
];
