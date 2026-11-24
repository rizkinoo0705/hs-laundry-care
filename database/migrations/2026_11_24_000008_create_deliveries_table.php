<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // jemput | antar
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
            $table->text('address');
            $table->dateTime('scheduled_at');
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('terjadwal'); // terjadwal | jalan | selesai | batal
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
