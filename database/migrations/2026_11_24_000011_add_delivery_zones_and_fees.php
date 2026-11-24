<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan umum (lokasi outlet, aturan gratis ongkir, dll).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Zona tarif berdasarkan jarak dari outlet.
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->decimal('max_km', 6, 2);          // berlaku sampai jarak ini
            $table->unsignedInteger('fee');           // ongkir per perjalanan
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('distance_km', 6, 2)->nullable()->after('longitude');
            $table->string('distance_source', 10)->nullable()->after('distance_km'); // jalan | lurus
            // Promo ongkir khusus pelanggan: none | free | percent
            $table->string('delivery_promo', 10)->default('none')->after('stamps');
            $table->unsignedInteger('delivery_promo_value')->default(0)->after('delivery_promo');
            $table->date('delivery_promo_until')->nullable()->after('delivery_promo_value');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('pickup')->default(false)->after('delivery_method'); // dijemput?
            $table->decimal('distance_km', 6, 2)->nullable()->after('pickup');
            $table->foreignId('delivery_zone_id')->nullable()->after('distance_km')->constrained('delivery_zones')->nullOnDelete();
            $table->unsignedTinyInteger('delivery_trips')->default(0)->after('delivery_zone_id');
            $table->unsignedInteger('delivery_fee')->default(0)->after('delivery_trips');
            $table->unsignedInteger('delivery_discount')->default(0)->after('delivery_fee');
            $table->string('delivery_note')->nullable()->after('delivery_discount');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('distance_km', 6, 2)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'distance_km']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_zone_id');
            $table->dropColumn(['pickup', 'distance_km', 'delivery_trips', 'delivery_fee', 'delivery_discount', 'delivery_note']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'distance_km', 'distance_source',
                'delivery_promo', 'delivery_promo_value', 'delivery_promo_until']);
        });

        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('settings');
    }
};
