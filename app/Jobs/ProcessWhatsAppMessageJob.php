<?php

namespace App\Jobs;

use App\Services\WhatsApp\IntentParsingService;
use App\Services\WhatsApp\ReportManagerService;
use App\Services\WhatsApp\WhatsAppResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param string $senderPhone
     * @param string $messageText
     * @param string $messageId
     */
    public function __construct(
        public string $senderPhone,
        public string $messageText,
        public string $messageId
    ) {}

    /**
     * Execute the job.
     *
     * @param IntentParsingService $intentParser
     * @param ReportManagerService $reportManager
     * @param WhatsAppResponseService $whatsappResponse
     * @return void
     */
    public function handle(
        IntentParsingService $intentParser,
        ReportManagerService $reportManager,
        WhatsAppResponseService $whatsappResponse
    ): void {
        try {
            // 1. تحليل النية واستخراج المقاصد عبر DeepSeek
            $parsedIntent = $intentParser->parseIntent($this->messageText, $this->senderPhone);

            // 2. استعلام قاعدة البيانات وتوليد التقرير المطلوب
            $reportResult = $reportManager->generateReport($parsedIntent);

            // 3. إرسال التقرير النهائي إلى المدير عبر الواتساب
            $whatsappResponse->sendTextMessage($this->senderPhone, $reportResult);

        } catch (Throwable $e) {
            Log::error('ProcessWhatsAppMessageJob Failed', [
                'message_id'   => $this->messageId,
                'sender_phone' => $this->senderPhone,
                'message_text' => $this->messageText,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            // إرسال رسالة تنبيهية آمنة للمدير
            $whatsappResponse->sendTextMessage(
                $this->senderPhone,
                "⚠️ عذراً، حدث عطل تقني أثناء استخراج التقرير المطلوب. تم تسجيل المشكلة للمراجعة."
            );
        }
    }
}
