<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueryHandlerRegistry
{
    /**
     * قائمة معالجات الاستعلامات المسجلة في النظام
     *
     * @var array<string, QueryHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * تسجيل معالج استعلام جديد
     */
    public function register(QueryHandlerInterface $handler): void
    {
        $this->handlers[$handler->getIntentName()] = $handler;
    }

    /**
     * تنفيذ الاستعلام المناسب وتسجيل خطوات التنفيذ
     */
    public function handle(array $parsedIntent): string
    {
        $intent = $parsedIntent['intent'] ?? 'unknown';

        Log::info(" [WA-Registry] استلام نية لمعالجتها", [
            'intent'  => $intent,
            'payload' => $parsedIntent,
        ]);

        if (isset($this->handlers[$intent])) {
            try {
                Log::info(" [WA-Registry] توجيه النية للـ Handler المسجل: " . get_class($this->handlers[$intent]));

                $response = $this->handlers[$intent]->handle($parsedIntent);

                Log::info(" [WA-Registry] تم توليد الرد بنجاح", [
                    'intent'   => $intent,
                    'response' => $response,
                ]);

                return $response;

            } catch (Throwable $e) {
                Log::error("❌ [WA-Registry] خطأ أثناء تنفيذ الـ Handler [{$intent}]", [
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                    'payload' => $parsedIntent,
                ]);

                return "⚠️ تعذر استكمال الاستعلام حالياً بسبب خطأ غير متوقع. يرجى المحاولة لاحقاً.";
            }
        }

        Log::warning("⚠️ [WA-Registry] لم يتم العثور على Handler للنية: {$intent}");

        return $this->getFallbackResponse();
    }

    /**
     * جلب جميع المعالجات المسجلة
     */
    public function getRegisteredHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * الرد التوضيحي الافتراضي
     */
    protected function getFallbackResponse(): string
{
    return "🤖 *عذراً، لم أستطع فهم نوع الاستعلام المطلوب بدقة.*\n\n"
         . "💡 *إليك الخدمات المتاحة وكيفية البحث:* \n\n"
         . "1️⃣ *أرصدة الحسابات (عملاء وموردين):*\n"
         . "   • الصيغة: `رصيد [اسم العميل/المورد أو الهاتف]`\n"
         . "   • مثال: `كشف حساب شركة البركة` أو `رصيد 0912345678`\n\n"
         . "2️⃣ *المخزون والكميات المتوفرة:*\n"
         . "   • الصيغة: `رصيد [اسم الصنف أو الباركود]`\n"
         . "   • مثال: `رصيد بنر 130` أو `جرد ورق 70 جرام`\n\n"
         . "3️⃣ *تقارير المبيعات:*\n"
         . "   • الصيغة: `مبيعات [اليوم / أمس / التاريخ]`\n"
         . "   • مثال: `مبيعات اليوم` أو `مبيعات أمس في أمدرمان`\n\n"
         . "✍️ *جرب إرسال إحدى الصيغ أعلاه وسأجيبك فوراً.*";
}
}
