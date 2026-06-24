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
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            // بيانات الوحدة الأساسية
            $table->string('name')->unique()->comment('اسم الوحدة القياسي (مثال: حبة، كرتون، كيلو، متر)');
            $table->string('short_name')->nullable()->comment('الرمز أو الاختصار التجاري للوحدة (مثال: حبة -> ح، كيلو -> كجم)');

            // حالة النشاط للتحكم في ظهورها بالقوائم المنسدلة في الـ Front-end
            $table->boolean('is_active')->default(true)->comment('حالة نشاط الوحدة في النظام');

            $table->softDeletes(); // الحفاظ على السلامة الأرشيفية للأنظمة الكبيرة
            $table->timestamps();

            // الفهارس لتسريع عمليات جلب القوائم والفلترة
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
