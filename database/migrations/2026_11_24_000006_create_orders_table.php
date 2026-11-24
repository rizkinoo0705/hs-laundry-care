<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('mitra_id')->nullable()->constrained('mitras')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('diterima');
            $table->string('delivery_method', 10)->default('ambil'); // ambil | antar
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->decimal('total_kg', 10, 2)->default(0);
            $table->string('payment_status', 10)->default('belum'); // belum | lunas
            $table->string('payment_method', 20)->nullable();       // tunai | transfer | qris
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('stamp_awarded')->default(false);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->foreignId('mitra_payout_id')->nullable()->constrained('mitra_payouts')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
