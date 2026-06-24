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
        Schema::create('item_stocks', function (Blueprint $table) {
            $table->id();

            // ربط كمية الصنف بالمخزن المتواجد فيه
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');

            // الكمية اللحظية الحالية مقاسة بالوحدة الصغرى دائماً لتوحيد الحسابات
            $table->decimal('current_quantity', 15, 4)->default(0.0000)->comment('الكمية الحالية للصنف داخل هذا المخزن بالوحدة الصغرى');

            $table->timestamps();

            // عمل مفتاح فريد مركب (Unique Composite Index) لضمان عدم تكرار الصنف في نفس المخزن
            // ولتسريع عملية البحث والاستعلام اللحظي لأقصى درجة ممكنة
            $table->unique(['item_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_stocks');
    }
};
