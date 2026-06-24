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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // الربط الشجري (دعم الأب والابن)
            // استخدام nullable لأن التصنيفات الرئيسية على المستوى الأول ليس لها أب
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('restrict');

            // بيانات التصنيف الأساسية
            $table->string('name')->unique()->comment('اسم التصنيف (مثال: قطع غيار، مواد غذائية)');

            // حقل المسار المفهرس (Materialized Path) لسرعة البحث الصاروخية في الفروع (مثال: 1/5/12/)
            $table->string('path')->nullable()->comment('مسار الشجرة الكامل مخرناً بالمعرفات لسرعة الفلترة الكبيرة');

            // حالة النشاط للتحكم في العرض بالشاشات
            $table->boolean('is_active')->default(true)->comment('حالة نشاط التصنيف في النظام');

            $table->softDeletes(); // الالتزام بقاعدتنا الصارمة للملفات اللوجستية
            $table->timestamps();

            // الفهارس (Indexes) لتسريع عمليات الفرز والبحث الشجري
            $table->index('parent_id');
            $table->index('path');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
