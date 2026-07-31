<?php

namespace App\Services\WhatsApp\Contracts;

interface QueryHandlerInterface
{
    /**
     * اسم النية الفريدة التي يعالجها الكلاس (مثال: sales_report, inventory_report)
     *
     * @return string
     */
    public function getIntentName(): string;

    /**
     * وصف دقيق وموجز للنية يُحقن تلقائياً في الـ System Prompt الخاص بالذكاء الاصطناعي
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * تنفيذ الاستعلام وإعادة نص الرد البسيط والمباشر للواتساب
     *
     * @param array $parsedIntent
     * @return string
     */
    public function handle(array $parsedIntent): string;
}
