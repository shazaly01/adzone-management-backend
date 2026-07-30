<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWhatsAppSender
{
    /**
     * Handle an incoming WhatsApp Webhook request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. استخراج معرف الشات الأصلي
        $rawSender = $request->input('from')
            ?? $request->input('chatId')
            ?? $request->input('sender.id')
            ?? $request->input('data.from')
            ?? $request->input('data.key.remoteJid')
            ?? $request->input('sender')
            ?? '';

        // 2. تجاهل رسائل المجموعات فوراً لمنع المعالجة الخاطئة
        $isGroup = $request->input('isGroupMsg')
            ?? $request->input('isGroup')
            ?? $request->input('data.isGroup')
            ?? str_contains($rawSender, '@g.us');

        if ($isGroup) {
            return response()->json([
                'status'  => 'ignored',
                'message' => 'Group messages are ignored.'
            ], Response::HTTP_OK);
        }

        if (empty($rawSender)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid WhatsApp webhook payload structure.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // 3. تنظيف رقم المرسل من الأحرف والرموز والزواحف (@c.us, @s.whatsapp.net, إلخ)
        $cleanSender = strtok($rawSender, '@');
        $senderDigits = preg_replace('/[^0-9]/', '', $cleanSender);

        // 4. قراءة وتنظيف رقم المدير من الإعدادات
        $rawAdminPhone = config('services.wppconnect.admin_phone')
            ?? config('services.whatsapp.admin_phone')
            ?? '';
        $adminDigits = preg_replace('/[^0-9]/', '', $rawAdminPhone);

        if (empty($adminDigits) || empty($senderDigits)) {
            return response()->json([
                'status'  => 'unauthorized',
                'message' => 'Unauthorized WhatsApp sender or admin phone misconfigured.'
            ], Response::HTTP_FORBIDDEN);
        }

        // 5. مطابقة مرنة للرقم (تتجاوز اختلاف مفتاح الدولة مثل +249 أو 00249 أو 249)
        if (!$this->isPhoneMatch($senderDigits, $adminDigits)) {
            return response()->json([
                'status'  => 'unauthorized',
                'message' => 'Unauthorized WhatsApp sender.'
            ], Response::HTTP_FORBIDDEN);
        }

        // 6. حفظ الرقم المنظف في attributes الطلب للاستخدام في الـ Controller
        $request->attributes->set('sender_phone', $senderDigits);

        return $next($request);
    }

    /**
     * Compare two phone numbers flexibly by checking the trailing digits.
     *
     * @param string $phone1
     * @param string $phone2
     * @return bool
     */
    protected function isPhoneMatch(string $phone1, string $phone2): bool
    {
        if ($phone1 === $phone2) {
            return true;
        }

        // مقارنة آخر 9 أرقام لضمان عدم التأثر باختلاف الصفر الدولي (+ / 00)
        $minLen = 9;
        if (strlen($phone1) >= $minLen && strlen($phone2) >= $minLen) {
            return substr($phone1, -$minLen) === substr($phone2, -$minLen);
        }

        return false;
    }
}
