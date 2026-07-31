<?php

namespace App\Services\WhatsApp;

class ReportManagerService
{
    protected QueryHandlerRegistry $registry;

    /**
     * حقن الـ QueryHandlerRegistry تلقائياً
     */
    public function __construct(QueryHandlerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * معالجة النية وإصدار الرد البسيط المناسب عبر تحويل الطلب لسجل الاستعلامات المركزي
     *
     * @param array $parsedIntent
     * @return string
     */
    public function generateReport(array $parsedIntent): string
    {
        return $this->registry->handle($parsedIntent);
    }
}
