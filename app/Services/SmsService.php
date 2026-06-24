<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Beneficiary;
use App\Models\Area;
use App\Jobs\SendSmsJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendIndividual(Beneficiary $beneficiary, string $content, $senderId = null)
    {
        if (empty($beneficiary->phone)) return false;

        $message = Message::create([
            'beneficiary_id' => $beneficiary->id,
            'phone'          => $beneficiary->phone,
            'content'        => $content,
            'type'           => 'individual',
            'sender_id'      => $senderId,
            'status'         => 'pending',
        ]);

        SendSmsJob::dispatch($message);
        return true;
    }

    public function sendToArea(Area $area, string $content, $senderId = null)
    {
        $allAreaIds = $this->getAllAreaIds($area);

        $beneficiaries = Beneficiary::whereIn('area_id', $allAreaIds)
                                    ->whereNotNull('phone')
                                    ->where('phone', '!=', '')
                                    ->get();

        foreach ($beneficiaries as $beneficiary) {
            $this->sendIndividual($beneficiary, $content, $senderId);
        }

        return $beneficiaries->count();
    }

    private function getAllAreaIds(Area $area): array
    {
        $ids = [$area->id];
        $area->loadMissing('children');
        foreach ($area->children as $child) {
            $ids = array_merge($ids, $this->getAllAreaIds($child));
        }
        return $ids;
    }

    /**
     * --------------------------------------------------------
     * محرك الإرسال الفعلي (الربط مع شركة رسائل)
     * --------------------------------------------------------
     */
    public function dispatchToProvider(Message $message)
    {
        try {
            $token = $this->getRasaelToken();
            $formattedPhone = $this->formatLibyanPhoneNumber($message->phone);

            $payload = [
                'phoneNumber' => $formattedPhone,
                'message'     => $message->content,
                'senderID'    => config('services.rasael.sender_id'),
            ];

            // إرسال الطلب مع التوكن
            $response = Http::withToken($token)
                ->acceptJson()
                ->post('https://rasael.almasafa.ly/api/sms/Send', $payload);

            // التحقق من النجاح
            if ($response->successful() && !str_contains(strtolower($response->body()), 'error')) {
                $message->update(['status' => 'sent', 'error_log' => null]);
                return true;
            }

            $statusCode = $response->status();
            $responseBody = $response->body();


            throw new \Exception("Rasael API Error (Status {$statusCode}): " . $responseBody);

        } catch (\Exception $e) {
            $message->update([
                'status'    => 'failed',
                'error_log' => $e->getMessage()
            ]);
            Log::error("SMS Failed ID {$message->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * مستخلص التوكن الذكي
     */
    private function getRasaelToken()
    {
        return Cache::remember('rasael_auth_token', 7200, function () {

            $response = Http::acceptJson()->post('https://rasael.almasafa.ly/api/MasafaRasaelLogin', [
                'username' => config('services.rasael.username'),
                'password' => config('services.rasael.password'),
            ]);

            if ($response->successful()) {
                $body = $response->body();

                // 1. محاولة قراءة التوكن إذا كان بصيغة JSON
                $json = json_decode($body, true);
                if (is_array($json) && isset($json['token'])) {
                    $token = $json['token'];
                } else {
                    // 2. إذا كان نصاً مباشراً، نقوم بتنظيفه من أي علامات تنصيص أو مسافات
                    $token = trim($body, " \t\n\r\0\x0B\"");
                }

                if (empty($token)) {
                    throw new \Exception('الـ API رد بنجاح ولكن التوكن فارغ!');
                }


                return $token;
            }

            throw new \Exception('فشل تسجيل الدخول: ' . $response->body());
        });
    }
    /**
     * دالة لتنسيق أرقام الهواتف الليبية
     */
    private function formatLibyanPhoneNumber($phone)
    {
        $cleanPhone = ltrim(preg_replace('/[^0-9]/', '', $phone), '0');

        if (str_starts_with($cleanPhone, '9')) {
            return '218' . $cleanPhone;
        }

        return $cleanPhone;
    }
}
