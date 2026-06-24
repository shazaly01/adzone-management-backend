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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('category_path')->nullable();
            $table->string('name');
            $table->string('item_type')->default('product'); // product (صنف مخزني), service (خدمة)
            $table->decimal('profit_margin', 8, 2)->default(0.00);

            // ربط الصنف بالوحدة الأساسية الصغرى (قاعدة الجرد والتحويل المخزني)
            $table->foreignId('base_unit_id')->constrained('units')->cascadeOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); // تفعيل الحذف المرن بناء على المعمارية القياسية لنظامك
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
