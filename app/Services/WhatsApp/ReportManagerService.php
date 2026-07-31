<?php

namespace App\Services\WhatsApp;

class ReportManagerService
{
    protected QueryHandlerRegistry $registry;

    /**
     * دعم الإنشاء المباشر new ReportManagerService() أو الـ Injection
     */
    public function __construct(?QueryHandlerRegistry $registry = null)
    {
        $this->registry = $registry ?? app(QueryHandlerRegistry::class);
    }

    /**
     * معالجة النية وإصدار الرد
     */
    public function generateReport(array $parsedIntent): string
    {
        return $this->registry->handle($parsedIntent);
    }
}
