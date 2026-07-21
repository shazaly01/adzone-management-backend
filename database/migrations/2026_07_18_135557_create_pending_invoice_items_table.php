<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // مسح مخلفات المحاولة السابقة المكسورة لتهيئة الجدول بأمان
        Schema::dropIfExists('pending_invoice_items');

        Schema::create('pending_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->nullable()->constrained('items')->onDelete('set null');
            $table->text('raw_text');
            $table->json('ai_output')->nullable();
            $table->decimal('height', 10, 2)->default(0.00);
            $table->decimal('width', 10, 2)->default(0.00);
            $table->integer('quantity')->default(1);
            $table->decimal('price', 15, 2)->default(0.00);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_invoice_items');
    }
};
