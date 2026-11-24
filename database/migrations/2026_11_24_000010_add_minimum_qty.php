<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Minimum yang ditagih, mis. 3 kg untuk kiloan. Kosong = tanpa minimum.
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('min_qty', 8, 2)->nullable()->after('price');
        });

        // qty = berat/jumlah sebenarnya, charged_qty = yang ditagih (setelah minimum).
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('charged_qty', 10, 2)->nullable()->after('qty');
        });

        DB::table('order_items')->whereNull('charged_qty')->update(['charged_qty' => DB::raw('qty')]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('charged_qty');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('min_qty');
        });
    }
};
