<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitras')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->decimal('total_kg', 10, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->timestamp('paid_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_payouts');
    }
};
