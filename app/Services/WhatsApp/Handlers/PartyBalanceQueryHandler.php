<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class PartyBalanceQueryHandler implements QueryHandlerInterface
{
    /**
     * اسم النية الفريدة التي يعالجها الكلاس
     */
    public function getIntentName(): string
    {
        return 'party_balance';
    }

    /**
     * وصف دقيق وموجز للنية يُحقن تلقائياً في الـ System Prompt الخاص بالذكاء الاصطناعي
     */
    public function getDescription(): string
    {
        return 'استعلام عن رصيد حساب عميل أو مورد (مثل: رصيد شركة البركة، كم على العميل أحمد، كم للمورد علي)';
    }

    /**
     * تنفيذ الاستعلام وإعادة نص الرد البسيط والمباشر للواتساب
     *
     * @param array $parsedIntent
     * @return string
     */
    public function handle(array $parsedIntent): string
    {
        $partyName = trim($parsedIntent['party_name'] ?? '');
        $partyType = strtolower(trim($parsedIntent['party_type'] ?? 'all'));
        $branchConnections = $parsedIntent['branch_connections'] ?? ['branch_main'];

        if (empty($partyName)) {
            return "⚠️ يرجى تحديد اسم العميل أو المورد للاستعلام عن الرصيد.";
        }

        $results = collect();

        foreach ($branchConnections as $connection) {
            $branchResults = $this->searchInBranch($connection, $partyName, $partyType);
            if (!empty($branchResults['items']) && $branchResults['items']->isNotEmpty()) {
                $results->put($connection, $branchResults);
            }
        }

       if ($results->isEmpty()) {
    return "❌ *لم نجد أي عميل أو مورد يطابق*: \"{$partyName}\"\n\n"
         . "💡 *نصائح للوصول لنتيجة دقيقة:*\n"
         . "• جرب البحث برقم الهاتف أو كود الحساب (مثال: `رصيد 0912345678`).\n"
         . "• تأكد من كتابة الاسم بدون أخطاء إملائية أو اختصارات.\n"
         . "• اكتب كلمة واحدة من اسم العميل بدلاً من الاسم الكامل.\n\n"
         . "📌 *استعلامات أخرى يمكنك تجربتها:*\n"
         . "• `مبيعات اليوم` | `رصيد بنر 130`";
}

        return $this->formatWhatsAppReport($partyName, $results);
    }

    /**
     * البحث في قاعدة بيانات فرع محدد عن العميل أو المورد مع الترتيب والحد الأقصى
     */
    protected function searchInBranch(string $connection, string $search, string $partyType): array
    {
        $maxResults = 4;
        $found = collect();
        $totalMatches = 0;

        $isNumericSearch = is_numeric($search);

        // 1. البحث في العملاء
        if (in_array($partyType, ['customer', 'all'])) {
            $customerQuery = Customer::on($connection);

            if ($isNumericSearch) {
                $customerQuery->where('phone', 'like', "%{$search}%")
                              ->orWhere('code', 'like', "%{$search}%");
            } else {
                $customerQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                })->orderByRaw("
                    CASE
                        WHEN name = ? THEN 1
                        WHEN name LIKE ? THEN 2
                        ELSE 3
                    END
                ", [$search, "{$search}%"]);
            }

            $totalMatches += $customerQuery->count();
            $customers = $customerQuery->take($maxResults)->get();

            foreach ($customers as $customer) {
                $found->push([
                    'type'       => 'customer',
                    'type_label' => 'عميل',
                    'name'       => $customer->name,
                    'code'       => $customer->code ?? 'N/A',
                    'phone'      => $customer->phone ?? 'N/A',
                    'balance'    => (float) $customer->current_balance,
                ]);
            }
        }

        // 2. البحث في الموردين
        if (in_array($partyType, ['supplier', 'all'])) {
            $supplierQuery = Supplier::on($connection);

            if ($isNumericSearch) {
                $supplierQuery->where('phone', 'like', "%{$search}%")
                              ->orWhere('code', 'like', "%{$search}%");
            } else {
                $supplierQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                })->orderByRaw("
                    CASE
                        WHEN name = ? THEN 1
                        WHEN name LIKE ? THEN 2
                        ELSE 3
                    END
                ", [$search, "{$search}%"]);
            }

            $totalMatches += $supplierQuery->count();
            $suppliers = $supplierQuery->take($maxResults)->get();

            foreach ($suppliers as $supplier) {
                $found->push([
                    'type'       => 'supplier',
                    'type_label' => 'مورد',
                    'name'       => $supplier->name,
                    'code'       => $supplier->code ?? 'N/A',
                    'phone'      => $supplier->phone ?? 'N/A',
                    'balance'    => (float) $supplier->current_balance,
                ]);
            }
        }

        // تحديد أعلى N نتائج إجمالية فقط لكل فرع
        $limitedItems = $found->take($maxResults);

        return [
            'total_count' => $totalMatches,
            'has_more'    => $totalMatches > $maxResults,
            'items'       => $limitedItems,
        ];
    }

    /**
     * تنسيق مخرجات التقارير لشاشة الواتساب بطريقة موجزة ومحاسبية دقيقة
     */
    protected function formatWhatsAppReport(string $search, Collection $results): string
    {
        $output = "📊 *تقرير أرصدة الحسابات*: _{$search}_\n";
        $output .= "-----------------------------------\n";

        $totalMoreParties = 0;

        foreach ($results as $branch => $branchData) {
            $branchLabel = ucfirst(str_replace(['branch_', '_'], ['', ' '], $branch));
            $output .= "🏢 *الفرع*: {$branchLabel}\n";

            $items = $branchData['items'];
            foreach ($items as $item) {
                $balance = $item['balance'];
                $absBalance = number_format(abs($balance), 2);

                if ($item['type'] === 'customer') {
                    if ($balance > 0) {
                        $status = "🔴 *عليه*: {$absBalance}";
                    } elseif ($balance < 0) {
                        $status = "🟢 *له*: {$absBalance}";
                    } else {
                        $status = "⚪ *خالي من الديون*: 0.00";
                    }
                } else {
                    if ($balance > 0) {
                        $status = "🔴 *له*: {$absBalance}";
                    } elseif ($balance < 0) {
                        $status = "🟢 *عليه*: {$absBalance}";
                    } else {
                        $status = "⚪ *خالي من الديون*: 0.00";
                    }
                }

                $output .= "• [{$item['type_label']}] *{$item['name']}*\n";
                $output .= "  └ الرصيد: {$status}\n";
            }

            if (!empty($branchData['has_more'])) {
                $moreInBranch = $branchData['total_count'] - $items->count();
                $totalMoreParties += $moreInBranch;
            }

            $output .= "\n";
        }

        if ($totalMoreParties > 0) {
            $output .= "-----------------------------------\n";
            $output .= "💡 *ملاحظة*: توجد *{$totalMoreParties} حسابات أخرى* تطابق كلمة \"{$search}\".\n";
            $output .= "لتحديد الحساب بقة، يرجى كتابة (الاسم كاملاً)، أو (رقم كود الحساب)، أو (رقم الهاتف).\n";
        }

        return trim($output);
    }
}
