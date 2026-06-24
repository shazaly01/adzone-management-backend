<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $message;

    /**
     * استلام كائن الرسالة عند استدعاء المهمة
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * تنفيذ المهمة في الخلفية (لارافيل سيقوم بحقن SmsService تلقائياً هنا)
     */
    public function handle(SmsService $smsService)
    {
        // حماية إضافية: إذا كانت الرسالة قد أُرسلت مسبقاً لا نرسلها مرة أخرى
        if ($this->message->status === 'sent') {
            return;
        }

        // استدعاء محرك الإرسال الفعلي
        $smsService->dispatchToProvider($this->message);
    }
}
