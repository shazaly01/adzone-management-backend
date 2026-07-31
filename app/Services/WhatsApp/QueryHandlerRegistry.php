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
     * تسجيل معالج استعلام جديد داخل السجل
     *
     * @param QueryHandlerInterface $handler
     * @return void
     */
    public function register(QueryHandlerInterface $handler): void
    {
        $this->handlers[$handler->getIntentName()] = $handler;
    }

    /**
     * تنفيذ الاستعلام المناسب بناءً على النية المحللة
     *
     * @param array $parsedIntent
     * @return string
     */
    public function handle(array $parsedIntent): string
    {
        $intent = $parsedIntent['intent'] ?? 'unknown';

        if (isset($this->handlers[$intent])) {
            try {
                return $this->handlers[$intent]->handle($parsedIntent);
            } catch (Throwable $e) {
                Log::error("QueryHandlerRegistry Exception [{$intent}]: " . $e->getMessage(), [
                    'exception' => $e,
                    'payload'   => $parsedIntent,
                ]);

                return "⚠️ تعذر استكمال الاستعلام حالياً بسبب خطأ غير متوقع. يرجى المحاولة لاحقاً.";
            }
        }

        return $this->getFallbackResponse();
    }

    /**
     * جلب جميع المعالجات المسجلة لاستخدامها في بناء Prompt الذكاء الاصطناعي ديناميكياً
     *
     * @return array<string, QueryHandlerInterface>
     */
    public function getRegisteredHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * الرد التوضيحي الافتراضي عند عدم فهم النية
     *
     * @return string
     */
    protected function getFallbackResponse(): string
    {
        return "عذراً، لم أستطع فهم نوع الاستعلام المطلوب بدقة.\n\n"
             . "💡 **يمكنك الطلب بصيغة بسيطة مثل:**\n"
             . "• مبيعات اليوم\n"
             . "• رصيد بنر 130 في أمدرمان\n"
             . "• كشف حساب شركة البركة";
    }
}
