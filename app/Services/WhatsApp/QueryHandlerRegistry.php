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
     * الرد التوضيحي الافتراضي (محدث ليشمل جميع التقارير التشغيلية)
     */
    protected function getFallbackResponse(): string
    {
        return "🤖 *عذراً، لم أستطع فهم نوع الاستعلام المطلوب بدقة.*\n\n"
             . "💡 *إليك التقارير والخدمات المتاحة وكيفية الاستعلام:* \n\n"
             . "1️⃣ *تقارير المبيعات:*\n"
             . "   • مثال: `مبيعات اليوم` أو `مبيعات فرع أمدرمان`\n\n"
             . "2️⃣ *أرصدة الحسابات وآخر الفواتير:*\n"
             . "   • مثال: `كشف حساب شركة البركة` أو `آخر فاتورة لعميل أحمد`\n\n"
             . "3️⃣ *كبار المدينين (الديون):*\n"
             . "   • مثال: `أعلى 10 عملاء مدينين` أو `أكبر الديون في فرع مدني`\n\n"
             . "4️⃣ *المخزون وتقرير النواقص:*\n"
             . "   • مثال: `رصيد بنر 130` أو `تقرير النواقص في أمدرمان`\n\n"
             . "5️⃣ *سحب واستهلاك الخامات:*\n"
             . "   • مثال: `استهلاك الخامات هذا الشهر` أو `سحب الأمتار في بورتسودان`\n\n"
             . "✍️ *جرب إرسال إحدى الصيغ أعلاه وسأجيبك فوراً.*";
    }
}
