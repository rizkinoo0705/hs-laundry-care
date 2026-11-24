<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Petugas lapangan/produksi (tidak harus punya akun login).
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('position', 20)->default('umum'); // umum | produksi | kurir
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamp('paid_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Komisi petugas per kg/pcs layanan.
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('staff_commission')->default(0)->after('min_qty');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('user_id')->constrained('staff')->nullOnDelete();
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('courier_id')->constrained('staff')->nullOnDelete();
        });

        // Buku komisi: dicatat saat pekerjaan selesai, tarif dikunci saat itu.
        Schema::create('staff_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->cascadeOnDelete();
            $table->string('type', 10); // layanan | jemput | antar
            $table->string('description');
            $table->decimal('qty', 10, 2)->default(1);
            $table->unsignedInteger('rate');
            $table->unsignedBigInteger('amount');
            $table->timestamp('earned_at');
            $table->foreignId('staff_payout_id')->nullable()->constrained('staff_payouts')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'staff_payout_id']);
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_commissions');

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('staff_commission');
        });

        Schema::dropIfExists('staff_payouts');
        Schema::dropIfExists('staff');
    }
};
