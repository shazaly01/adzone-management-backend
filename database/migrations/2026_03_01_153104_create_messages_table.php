<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // محتوى الرسالة النصي
            $table->text('content');

            // رقم الهاتف الذي أُرسلت إليه الرسالة (نحتفظ به للتوثيق حتى لو تغير رقم المستفيد لاحقاً)
            $table->string('phone');

            // نوع الرسالة: فردية، منطقة (جماعية)، أو آلية (عند الصرف)
            $table->enum('type', ['individual', 'area', 'automated']);

            // حالة الإرسال
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');

            // العلاقات (اختيارية) لمعرفة لمن أُرسلت الرسالة
            // من قام بإرسال الرسالة (تكون null إذا كانت آلية)
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();

            // في حال فشل الإرسال، نحتفظ برسالة الخطأ من مزود الخدمة
            $table->text('error_log')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
