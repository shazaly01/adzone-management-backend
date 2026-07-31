<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use App\Models\Sale;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class LatestInvoiceQueryHandler implements QueryHandlerInterface
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

    /**
     * تسميات طرق الدفع
     */
    protected array $paymentTypeLabels = [
        'cash'   => 'نقدي 💵',
        'card'   => 'شبكة / بنكك 💳',
        'credit' => 'آجل / ذمم 📝',
    ];

    /**
     * تسميات حالات الورشة والإنتاج
     */
    protected array $productionStatusLabels = [
        Sale::STATUS_PENDING    => 'قيد الانتظار ⏳',
        Sale::STATUS_PROCESSING => 'جاري التشغيل ⚙️',
        Sale::STATUS_ON_HOLD    => 'معلق 🛑',
        Sale::STATUS_COMPLETED  => 'تم التنفيذ بالكامل ✅',
    ];

    public function getIntentName(): string
    {
        return 'latest_invoice';
    }

    public function getDescription(): string
    {
        return 'استعلام عن تفاصيل آخر فاتورة مبيعات لعميل محدد شاملة المقاسات (الطول والعرض)، اسم المصمم، وحالة الإنتاج.';
    }

    public function handle(array $parsedIntent): string
    {
        $search = trim($parsedIntent['party_name'] ?? $parsedIntent['customer'] ?? $parsedIntent['search'] ?? '');
        $targetBranch = $parsedIntent['branch'] ?? 'all';

        if (empty($search)) {
            return "⚠️ يرجى تحديد اسم العميل للاستعلام عن آخر فاتورة.";
        }

        $connectionsToQuery = [];
        if ($targetBranch === 'all' || !isset($this->branchConnections[$targetBranch])) {
            $connectionsToQuery = $this->branchConnections;
        } else {
            $connectionsToQuery[$targetBranch] = $this->branchConnections[$targetBranch];
        }

        $latestInvoice = null;
        $foundBranchKey = null;

        // البحث عن أحدث فاتورة للعميل في الفروع المطلوبة
        foreach ($connectionsToQuery as $branchKey => $connectionName) {
            if (!Config::get("database.connections.{$connectionName}")) {
                continue;
            }

            try {
                $invoice = Sale::on($connectionName)
                    ->with([
                        'customer',
                        'designer',
                        'items.item',
                        'items.itemUnit.unit',
                    ])
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($search) {
                        $q->where('customer_name_text', 'like', "%{$search}%")
                          ->orWhereHas('customer', function ($cQ) use ($search) {
                              $cQ->where('name', 'like', "%{$search}%")
                                 ->orWhere('phone', 'like', "%{$search}%");
                          });
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($invoice) {
                    // مقارنة التواريخ لاختيار أحدث فاتورة في حال كان البحث في كافة الفروع
                    if (!$latestInvoice || Carbon::parse($invoice->created_at)->gt(Carbon::parse($latestInvoice->created_at))) {
                        $latestInvoice = $invoice;
                        $foundBranchKey = $branchKey;
                    }
                }
            } catch (Throwable $e) {
                Log::error("LatestInvoiceQueryHandler Error [{$branchKey}]: " . $e->getMessage());
            }
        }

        if (!$latestInvoice) {
            $branchNotice = ($targetBranch !== 'all' && isset($this->branchLabels[$targetBranch]))
                ? " في *{$this->branchLabels[$targetBranch]}*"
                : "";

            return "❌ *لم نجد أي فواتير مسجلة للعميل*: \"{$search}\"{$branchNotice}.";
        }

        return $this->formatWhatsAppInvoice($latestInvoice, $foundBranchKey);
    }

    /**
     * تنسيق الفاتورة للعرض التفصيلي المخصص للدعاية والإعلان
     */
    protected function formatWhatsAppInvoice(Sale $sale, string $branchKey): string
    {
        $branchLabel = $this->branchLabels[$branchKey] ?? $branchKey;
        $customerName = $sale->customer->name ?? $sale->customer_name_text ?? 'عميل نقدي';
        $invoiceDate = $sale->invoice_date ? Carbon::parse($sale->invoice_date)->format('Y/m/d h:i A') : 'غير محدد';
        $paymentLabel = $this->paymentTypeLabels[$sale->payment_type] ?? $sale->payment_type;
        $statusLabel = $this->productionStatusLabels[$sale->production_status] ?? $sale->production_status;

        $output = "🧾 *تفاصيل آخر فاتورة مبيعات*\n";
        $output .= "-----------------------------------\n";
        $output .= "🏢 *الفرع*: {$branchLabel}\n";
        $output .= "📄 *رقم الفاتورة*: #{$sale->invoice_number}\n";
        $output .= "👤 *العميل*: {$customerName}\n";
        $output .= "📅 *التاريخ*: {$invoiceDate}\n";
        $output .= "⚙️ *حالة الورشة*: *{$statusLabel}*\n";

        if ($sale->designer) {
            $output .= "🎨 *المصمم*: {$sale->designer->name}\n";
        }

        $output .= "-----------------------------------\n";
        $output .= "📦 *بيانات اللوحات والأصناف*:\n\n";

        foreach ($sale->items as $index => $item) {
            $itemNum = $index + 1;
            $itemName = $item->item->name ?? 'صنف غير محدد';
            $unitName = $item->itemUnit?->unit?->name ?? 'وحدة';
            $qty = (float) $item->quantity;
            $price = number_format((float) $item->unit_price, 0);
            $total = number_format((float) $item->grand_total, 0);

            $output .= "*{$itemNum}️⃣ {$itemName}*\n";

            // عرض المقاسات والأبعاد (طول × عرض) إذا كان الصنف مترياً
            if ($item->length !== null && $item->width !== null && ((float)$item->length > 0 || (float)$item->width > 0)) {
                $length = (float) $item->length;
                $width = (float) $item->width;
                $output .= "   📐 المقاس: *{$length} × {$width} متر*\n";
            }

            $output .= "   ├ الكمية: {$qty} {$unitName}\n";
            $output .= "   ├ السعر: {$price} SDG\n";
            $output .= "   └ الإجمالي: *{$total} SDG*\n\n";
        }

        $output .= "-----------------------------------\n";

        if ((float) $sale->discount_amount > 0) {
            $output .= "🏷️ *خصم*: " . number_format((float) $sale->discount_amount, 0) . " SDG\n";
        }

        $output .= "💵 *صافي الفاتورة*: *" . number_format((float) $sale->grand_total, 0) . " SDG*\n";
        $output .= "💳 *طريقة الدفع*: {$paymentLabel}\n";

        if (!empty($sale->notes)) {
            $output .= "📝 *ملاحظات*: _{$sale->notes}_\n";
        }

        return trim($output);
    }
}
