<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Throwable;

class PartyBalanceQueryHandler implements QueryHandlerInterface
{
    /**
     * ربط مفاتيح الفروع بأسماء اتصالات قاعدة البيانات
     */
    protected array $branchConnections = [
        'omd'    => 'branch_main',
        'madani' => 'branch_1',
        'port1'  => 'branch_2',
        'port2'  => 'branch_3',
    ];

    /**
     * الأسماء المترجمة للفروع للعرض على الواتساب
     */
    protected array $branchLabels = [
        'omd'    => 'المركز الرئيسي (أمدرمان)',
        'madani' => 'فرع مدني',
        'port1'  => 'فرع بورتسودان 1',
        'port2'  => 'فرع بورتسودان 2',
    ];

    public function getIntentName(): string
    {
        return 'party_balance';
    }

    public function getDescription(): string
    {
        return 'استعلام عن رصيد حساب عميل أو مورد (مثال: رصيد العميل هيثم، حساب المورد شركة البركة)';
    }

    public function handle(array $parsedIntent): string
    {
        $search = trim($parsedIntent['party_name'] ?? $parsedIntent['search'] ?? '');
        $partyType = $parsedIntent['party_type'] ?? 'all'; // customer, supplier, or all
        $targetBranch = $parsedIntent['branch'] ?? 'all';

        if (empty($search)) {
            return "⚠️ يرجى تحديد اسم العميل أو المورد للاستعلام عن الرصيد.";
        }

        $connectionsToQuery = [];
        if ($targetBranch === 'all' || !isset($this->branchConnections[$targetBranch])) {
            $connectionsToQuery = $this->branchConnections;
        } else {
            $connectionsToQuery[$targetBranch] = $this->branchConnections[$targetBranch];
        }

        $normalizedSearch = $this->normalizeArabic($search);
        $results = collect();

        foreach ($connectionsToQuery as $branchKey => $connectionName) {
            if (!Config::get("database.connections.{$connectionName}")) {
                continue;
            }

            try {
                $branchResults = $this->searchInBranch($connectionName, $search, $normalizedSearch, $partyType);
                if (!empty($branchResults['parties']) && $branchResults['parties']->isNotEmpty()) {
                    $results->put($branchKey, $branchResults);
                }
            } catch (Throwable $e) {
                Log::error("PartyBalanceQueryHandler Error [{$branchKey}]: " . $e->getMessage());
            }
        }

        if ($results->isEmpty()) {
            $branchNotice = ($targetBranch !== 'all' && isset($this->branchLabels[$targetBranch]))
                ? " في *{$this->branchLabels[$targetBranch]}*"
                : "";

            return "❌ *لم نجد أي حساب يطابق*: \"{$search}\"{$branchNotice}\n\n"
                 . "💡 *نصائح للوصول لنتيجة دقيقة:*\n"
                 . "• اكتب الاسم أو جزءاً منه (مثال: `هيثم`).\n"
                 . "• تأكد من اختيار الفرع الصحيح.\n\n"
                 . "📌 *استعلامات أخرى يمكنك تجربتها:*\n"
                 . "• `رصيد شركة الأمل` | `مبيعات اليوم`";
        }

        return $this->formatWhatsAppReport($search, $results, $targetBranch);
    }

    /**
     * البحث في قاعدة بيانات الفرع عن العملاء والموردين
     */
    protected function searchInBranch(string $connection, string $rawSearch, string $normalizedSearch, string $partyType): array
    {
        $maxResults = 5;
        $foundParties = collect();

        // 1. البحث في جدول العملاء Customers
        if (in_array($partyType, ['all', 'customer'])) {
            $customers = DB::connection($connection)
                ->table('customers')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($rawSearch, $normalizedSearch) {
                    $q->where('name', 'like', "%{$rawSearch}%")
                      ->orWhere('phone', 'like', "%{$rawSearch}%")
                      ->orWhereRaw("
                            LOWER(
                                REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي'), 'ة', 'ه')
                            ) LIKE ?", ["%{$normalizedSearch}%"]);
                })
                ->take($maxResults)
                ->get()
                ->map(function ($c) {
                    return [
                        'name'    => $c->name,
                        'type'    => 'عميل',
                        'balance' => (float) ($c->current_balance ?? $c->balance ?? 0),
                    ];
                });

            $foundParties = $foundParties->concat($customers);
        }

        // 2. البحث في جدول الموردين Suppliers
        if (in_array($partyType, ['all', 'supplier']) && $foundParties->count() < $maxResults) {
            $suppliers = DB::connection($connection)
                ->table('suppliers')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($rawSearch, $normalizedSearch) {
                    $q->where('name', 'like', "%{$rawSearch}%")
                      ->orWhere('phone', 'like', "%{$rawSearch}%")
                      ->orWhereRaw("
                            LOWER(
                                REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي'), 'ة', 'ه')
                            ) LIKE ?", ["%{$normalizedSearch}%"]);
                })
                ->take($maxResults - $foundParties->count())
                ->get()
                ->map(function ($s) {
                    return [
                        'name'    => $s->name,
                        'type'    => 'مورد',
                        'balance' => (float) ($s->current_balance ?? $s->balance ?? 0),
                    ];
                });

            $foundParties = $foundParties->concat($suppliers);
        }

        return [
            'total_count' => $foundParties->count(),
            'parties'     => $foundParties,
        ];
    }

    /**
     * تنسيق التقرير النهائي لشاشة الواتساب
     */
    protected function formatWhatsAppReport(string $search, Collection $results, string $targetBranch): string
    {
        $output = "👤 *رصيد حساب*: _{$search}_\n";
        $output .= "-----------------------------------\n";

        foreach ($results as $branchKey => $branchData) {
            $label = $this->branchLabels[$branchKey] ?? $branchKey;
            $output .= "🏢 *الفرع*: {$label}\n";

            foreach ($branchData['parties'] as $party) {
                $balance = $party['balance'];
                $formattedBalance = number_format(abs($balance), 0) . " SDG";

                // تحديد حالة الرصيد (له / عليه)
                $status = "";
                if ($balance > 0) {
                    $status = " (عليه / مدين)";
                } elseif ($balance < 0) {
                    $status = " (له / دائن)";
                } else {
                    $status = " (خالي الرصيد)";
                }

                $output .= "🔹 *{$party['name']}* ({$party['type']})\n";
                $output .= "  └ الرصيد الحالي: *{$formattedBalance}*{$status}\n";
            }

            $output .= "\n";
        }

        return trim($output);
    }

    /**
     * تطبيع النصوص العربية
     */
    protected function normalizeArabic(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);
        $text = preg_replace('/[أإآ]/u', 'ا', $text);
        $text = preg_replace('/ى/u', 'ي', $text);
        $text = preg_replace('/ة/u', 'ه', $text);

        return trim(mb_strtolower($text));
    }
}
