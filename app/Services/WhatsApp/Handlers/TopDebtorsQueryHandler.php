<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use App\Models\Customer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class TopDebtorsQueryHandler implements QueryHandlerInterface
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
        'omd'    => 'أمدرمان',
        'madani' => 'مدني',
        'port1'  => 'بورتسودان 1',
        'port2'  => 'بورتسودان 2',
    ];

    public function getIntentName(): string
    {
        return 'top_debtors';
    }

    public function getDescription(): string
    {
        return 'عرض أعلى 10 عملاء مدينين بأعلى الأرصدة والمديونيات المستحقة للشركة عبر الفروع.';
    }

    public function handle(array $parsedIntent): string
    {
        $targetBranch = $parsedIntent['branch'] ?? 'all';

        $connectionsToQuery = [];
        if ($targetBranch === 'all' || !isset($this->branchConnections[$targetBranch])) {
            $connectionsToQuery = $this->branchConnections;
        } else {
            $connectionsToQuery[$targetBranch] = $this->branchConnections[$targetBranch];
        }

        $debtorsList = [];

        foreach ($connectionsToQuery as $branchKey => $connectionName) {
            if (!Config::get("database.connections.{$connectionName}")) {
                continue;
            }

            try {
                $customers = Customer::on($connectionName)
                    ->whereNull('deleted_at')
                    ->where('current_balance', '>', 0)
                    ->orderBy('current_balance', 'desc')
                    ->take(10)
                    ->get();

                foreach ($customers as $customer) {
                    $key = $customer->phone ?: $customer->name;
                    if (!isset($debtorsList[$key])) {
                        $debtorsList[$key] = [
                            'name'    => $customer->name,
                            'phone'   => $customer->phone ?? 'غير مسجل',
                            'balance' => (float) $customer->current_balance,
                            'branch'  => $this->branchLabels[$branchKey] ?? $branchKey,
                        ];
                    } else {
                        // تجميع الأرصدة في حال وجود العميل في أكثر من فرع
                        $debtorsList[$key]['balance'] += (float) $customer->current_balance;
                    }
                }
            } catch (Throwable $e) {
                Log::error("TopDebtorsQueryHandler Error [{$branchKey}]: " . $e->getMessage());
            }
        }

        if (empty($debtorsList)) {
            return "✅ *ممتاز!* لا يوجد أي عملاء مدينين بمديونيات مسجلة حالياً.";
        }

        // فرز القائمة المجمعة تنازلياً وأخذ أعلى 10 فقط
        usort($debtorsList, fn($a, $b) => $b['balance'] <=> $a['balance']);
        $top10 = array_slice($debtorsList, 0, 10);

        return $this->formatWhatsAppOutput($top10, $targetBranch);
    }

    protected function formatWhatsAppOutput(array $debtors, string $targetBranch): string
    {
        $branchTitle = ($targetBranch !== 'all' && isset($this->branchLabels[$targetBranch]))
            ? "({$this->branchLabels[$targetBranch]})"
            : "(جميع الفروع)";

        $output = "🚨 *قائمة أعلى 10 عملاء مدينين {$branchTitle}*\n";
        $output .= "-----------------------------------\n";

        $totalDebt = 0;
        foreach ($debtors as $index => $debtor) {
            $rank = $index + 1;
            $name = $debtor['name'];
            $balance = number_format($debtor['balance'], 0);
            $totalDebt += $debtor['balance'];

            $output .= "{$rank}️⃣ *{$name}*\n";
            $output .= "   ├ المديونية: *{$balance} SDG*\n";
            $output .= "   └ الفرع: {$debtor['branch']}\n\n";
        }

        $output .= "-----------------------------------\n";
        $output .= "💰 *إجمالي مديونيات هذه القائمة*: *" . number_format($totalDebt, 0) . " SDG*";

        return trim($output);
    }
}
