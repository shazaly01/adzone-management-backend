<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\ItemStock;
use App\Models\Treasury;
use App\Models\Bank;
use App\Models\Store;
use App\Services\StockMovementService;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class SaleService
{
    protected $stockService;
    protected $journalService;

    public function __construct(StockMovementService $stockService, JournalEntryService $journalService)
    {
        $this->stockService = $stockService;
        $this->journalService = $journalService;
    }

    /**
     * جلب فواتير المبيعات ممررة عبر الـ Pagination مع تطبيق الفلاتر المتقدمة ونطاق التاريخ
     */
    public function getPaginatedSales(array $filters = [], int $perPage = 15)
    {
        // بناء الاستعلام الأساسي مع شحن كافة العلاقات لمنع الـ N+1 Query
        $query = Sale::with(['store', 'customer', 'user', 'items.item', 'items.itemUnit.unit', 'treasury', 'bank']);

        // 1. فلتر البحث السريع (رقم الفاتورة أو الملاحظات)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // 2. فلتر نوع المستند (فاتورة مبيعات / مردودات)
        if (!empty($filters['invoice_type'])) {
            $query->where('invoice_type', $filters['invoice_type']);
        }

        // 3. فلتر مخزن الصرف
        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        // 4. فلتر حساب العميل
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // 5. فلتر تاريخ البداية (من تاريخ)
        if (!empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }

        // 6. فلتر تاريخ النهاية (إلى تاريخ)
        if (!empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        // الترتيب من الأحدث للأقدم وإرجاع النتائج مصفحة
        return $query->latest('invoice_date')->paginate($perPage);
    }
    /**
     * معالجة وحفظ فاتورة مبيعات أو مرتجع كاشير جديدة بالكامل
     */
    public function createSale(array $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {

            $saleData = array_merge($data, ['user_id' => $userId]);
            $items = $saleData['items'];
            $unsetFields = ['items'];
            foreach ($unsetFields as $field) {
                unset($saleData[$field]);
            }

            $sale = Sale::create($saleData);

            foreach ($items as $item) {
                // جلب سطر الوحدة مع تحميل الصنف مسبقاً لفحص صفة الأبعاد دون N+1
                $itemUnit = ItemUnit::with(['unit', 'item'])
                    ->where('item_id', $item['item_id'])
                    ->where('id', $item['item_unit_id'])
                    ->firstOrFail();

                // حفظ السطر مع تخزين الطول والعرض وحالة التصميم الافتراضية
                $saleItem = $sale->items()->create([
                    'item_id'         => $item['item_id'],
                    'item_unit_id'    => $itemUnit->id,
                    'length'          => $itemUnit->item->is_dimensional ? ($item['length'] ?? null) : null,
                    'width'           => $itemUnit->item->is_dimensional ? ($item['width'] ?? null) : null,
                    'is_designed'     => $item['is_designed'] ?? false,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'subtotal'        => $item['subtotal'],
                    'discount_amount' => $item['discount_amount'] ?? 0.00,
                    'grand_total'     => $item['grand_total'],
                ]);

                // ديناميكية احتساب الكمية المخزنية: إذا كان الصنف مترياً يتم الخصم بناءً على (الطول × العرض × العدد) كمتر مربع
                $calculatedQty = $itemUnit->item->is_dimensional
                    ? ((float) ($item['length'] ?? 0) * (float) ($item['width'] ?? 0) * (float) $item['quantity'])
                    : (float) $item['quantity'];

                // إشارة الكمية للمبيعات: (سالب للمبيعات كحركة صادر، موجب للمرتجع كحركة وارد)
                $qty = $sale->invoice_type === 'sale' ? -$calculatedQty : $calculatedQty;

                $unitName = $itemUnit->unit->name ?? 'حبة';
                $unitFactor = (float) $itemUnit->conversion_factor;

                // تسجيل الحركة المخزنية وتحديث الـ Cache بالمعرف المطور
                $this->stockService->recordMovement(
                    $item['item_id'],
                    $sale->store_id,
                    $itemUnit->id,
                    $sale->invoice_type === 'sale' ? 'sales' : 'adjustment',
                    $sale->invoice_number,
                    $unitName,
                    $qty,
                    $unitFactor,
                    $item['unit_price'],
                    $sale->notes
                );
            }

            // توليد القيد المالي المزدوج (إيراد + تكلفة COGS المحدثة) وربطه بالفاتورة
            $journalEntry = $this->generateJournalEntry($sale);
            $sale->update(['journal_entry_id' => $journalEntry->id]);

            return $sale->load('items.itemUnit.unit');
        });
    }

    /**
     * معالجة تعديل وتحديث فاتورة مبيعات قائمة بشكل فوري وآمن
     */
    public function updateSale(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {

            $this->stockService->clearDocumentMovements($sale->invoice_number);

            // الحذف الآمن للقيد المالي القديم لمنع تكرار القيود أو تعليق المؤشرات
            if ($sale->journal_entry_id) {
                $oldEntry = JournalEntry::find($sale->journal_entry_id);
                if ($oldEntry) {
                    $this->journalService->deleteEntry($oldEntry);

                    // تنظيف السطر نهائياً لتفريغ الـ Unique Index وتجنب كراش التعديل 1062
                    $oldEntry->forceDelete();
                }
            }

            $items = $data['items'];
            unset($data['items']);
            $sale->update($data);

            $sale->items()->delete();

            foreach ($items as $item) {
                // جلب سطر الوحدة مع الصنف الرئيسي
                $itemUnit = ItemUnit::with(['unit', 'item'])
                    ->where('item_id', $item['item_id'])
                    ->where('id', $item['item_unit_id'])
                    ->firstOrFail();

                $saleItem = $sale->items()->create([
                    'item_id'         => $item['item_id'],
                    'item_unit_id'    => $itemUnit->id,
                    'length'          => $itemUnit->item->is_dimensional ? ($item['length'] ?? null) : null,
                    'width'           => $itemUnit->item->is_dimensional ? ($item['width'] ?? null) : null,
                    'is_designed'     => $item['is_designed'] ?? false,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'subtotal'        => $item['subtotal'],
                    'discount_amount' => $item['discount_amount'] ?? 0.00,
                    'grand_total'     => $item['grand_total'],
                ]);

                // إعادة احتساب الكمية المخزنية عند التعديل بناءً على المساحة المربعة (طول × عرض × كمية)
                $calculatedQty = $itemUnit->item->is_dimensional
                    ? ((float) ($item['length'] ?? 0) * (float) ($item['width'] ?? 0) * (float) $item['quantity'])
                    : (float) $item['quantity'];

                $qty = $sale->invoice_type === 'sale' ? -$calculatedQty : $calculatedQty;
                $unitName = $itemUnit->unit->name ?? 'حبة';
                $unitFactor = (float) $itemUnit->conversion_factor;

                $this->stockService->recordMovement(
                    $item['item_id'],
                    $sale->store_id,
                    $itemUnit->id,
                    $sale->invoice_type === 'sale' ? 'sales' : 'adjustment',
                    $sale->invoice_number,
                    $unitName,
                    $qty,
                    $unitFactor,
                    $item['unit_price'],
                    $sale->notes
                );
            }

            // إعادة بناء وتوليد القيد المالي المحدث وتثبيته
            $newEntry = $this->generateJournalEntry($sale);
            $sale->update(['journal_entry_id' => $newEntry->id]);

            return $sale->load('items.itemUnit.unit');
        });
    }

    /**
     * حذف أرشيفي لفاتورة المبيعات مع تصفية أثرها المخزني والمالي
     */
    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $this->stockService->clearDocumentMovements($sale->invoice_number);

            if ($sale->journal_entry_id) {
                $oldEntry = JournalEntry::find($sale->journal_entry_id);
                if ($oldEntry) {
                    $this->journalService->deleteEntry($oldEntry);
                }
            }

            $sale->delete();
        });
    }

    /**
     * توليد القيد المحاسبي المزدوج المتوافق مع حسابات الشجرة التجميعية الثابتة هاردكور والـ Sub-ledgers
     */
    private function generateJournalEntry(Sale $sale): JournalEntry
    {
        $lines = [];

        // 1. جلب حسابات الشجرة السيادية التجميعية بناءً على الأكواد الثابتة الهاردكور مضافاً إليها حقول المصمم
        $treasuryAccount        = Account::where('code', Account::CODE_TREASURY)->firstOrFail();
        $bankAccount            = Account::where('code', Account::CODE_BANKS)->firstOrFail();
        $customerAccount        = Account::where('code', Account::CODE_CUSTOMERS)->firstOrFail();
        $incomeAccount          = Account::where('code', Account::CODE_INCOME)->firstOrFail();
        $inventoryAccount       = Account::where('code', Account::CODE_INVENTORY)->firstOrFail();
        $cogsAccount            = Account::where('code', Account::CODE_COGS)->firstOrFail();
        $designExpenseAccount   = Account::where('code', Account::CODE_DESIGN_EXPENSES)->firstOrFail();
        $designersLedgerAccount = Account::where('code', Account::CODE_DESIGNERS_LEDGER)->firstOrFail();

        // 2. احتساب إجمالي تكلفة البضاعة المباعة (COGS) بناءً على الأمتار المربعة للأصناف المترية
        $totalInvoiceCost = 0.00;
        foreach ($sale->items()->with(['item', 'itemUnit'])->get() as $saleItem) {
            $unitFactor = (float) ($saleItem->itemUnit->conversion_factor ?? 1.00);

            $costQuantity = $saleItem->item->is_dimensional
                ? ((float) $saleItem->length * (float) $saleItem->width * (float) $saleItem->quantity)
                : (float) $saleItem->quantity;

            $baseQuantity = $costQuantity * $unitFactor;

            $baseUnitRow = ItemUnit::where('item_id', $saleItem->item_id)
                ->where('unit_id', $saleItem->item->base_unit_id)
                ->first();

            $itemUnitCost = $baseUnitRow ? (float) $baseUnitRow->cost : 0.00;
            $totalInvoiceCost += ($baseQuantity * $itemUnitCost);
        }

        // 3. صياغة كفتي القيد المحاسبي المالي بشكل محكم وديناميكي عبر نظام الـ Sub-ledgers الثابت
        if ($sale->invoice_type === 'sale') {

            // أ: قيد المبيعات التجاري (إيراد + تحصيل حسب نوع الدفع الفعلي والـ Sub-ledger المناسب له)
            if ($sale->payment_type === 'cash') {
                $lines[] = [
                    'account_id'      => $treasuryAccount->id,
                    'sub_ledger_type' => Treasury::class,
                    'sub_ledger_id'   => $sale->treasury_id,
                    'debit'           => $sale->grand_total,
                    'credit'          => 0.00,
                    'line_notes'      => 'تحصيل نقدي لقيمة مبيعات بموجب فاتورة رقم: ' . $sale->invoice_number,
                ];
            } elseif ($sale->payment_type === 'card') {
                $lines[] = [
                    'account_id'      => $bankAccount->id,
                    'sub_ledger_type' => Bank::class,
                    'sub_ledger_id'   => $sale->bank_id,
                    'debit'           => $sale->grand_total,
                    'credit'          => 0.00,
                    'line_notes'      => 'تحصيل بنكي/شبكة لقيمة مبيعات بموجب فاتورة رقم: ' . $sale->invoice_number,
                ];
            } else { // credit
                $lines[] = [
                    'account_id'      => $customerAccount->id,
                    'sub_ledger_type' => Customer::class,
                    'sub_ledger_id'   => $sale->customer_id,
                    'debit'           => $sale->grand_total,
                    'credit'          => 0.00,
                    'line_notes'      => 'مديونية عميل بموجب فاتورة مبيعات رقم: ' . $sale->invoice_number,
                ];
            }

            // إثبات جانب الإيراد التجميعي
            $lines[] = [
                'account_id'      => $incomeAccount->id,
                'sub_ledger_type' => null,
                'sub_ledger_id'   => null,
                'debit'           => 0.00,
                'credit'          => $sale->grand_total,
                'line_notes'      => 'إثبات إيراد مبيعات بموجب فاتورة رقم: ' . $sale->invoice_number,
            ];

            // ب: حقن قيد التكلفة اللحظي (COGS) مع تحديد المستودع كـ Sub-ledger لحساب المخزون التجميعي
            if ($totalInvoiceCost > 0) {
                $lines[] = [
                    'account_id'      => $cogsAccount->id,
                    'sub_ledger_type' => null,
                    'sub_ledger_id'   => null,
                    'debit'           => $totalInvoiceCost,
                    'credit'          => 0.00,
                    'line_notes'      => 'إثبات تكلفة البضاعة المباعة التلقائي لفاتورة رقم: ' . $sale->invoice_number,
                ];
                $lines[] = [
                    'account_id'      => $inventoryAccount->id,
                    'sub_ledger_type' => Store::class,
                    'sub_ledger_id'   => $sale->store_id,
                    'debit'           => 0.00,
                    'credit'          => $totalInvoiceCost,
                    'line_notes'      => 'تخفيض قيمة المستودعات بالبضاعة المباعة بموجب فاتورة رقم: ' . $sale->invoice_number,
                ];
            }

            // إثبات عمولة المصمم المستحقة لحظياً في المبيعات
            if ($sale->design_commission > 0 && $sale->designer_id) {
                $lines[] = [
                    'account_id'      => $designExpenseAccount->id,
                    'sub_ledger_type' => null,
                    'sub_ledger_id'   => null,
                    'debit'           => $sale->design_commission,
                    'credit'          => 0.00,
                    'line_notes'      => 'إثبات مصروف عمولة تصميم ناتجة عن الفاتورة رقم: ' . $sale->invoice_number,
                ];
                $lines[] = [
                    'account_id'      => $designersLedgerAccount->id,
                    'sub_ledger_type' => \App\Models\User::class,
                    'sub_ledger_id'   => $sale->designer_id,
                    'debit'           => 0.00,
                    'credit'          => $sale->design_commission,
                    'line_notes'      => 'استحقاق عمولة للمصمم بموجب فاتورة مبيعات رقم: ' . $sale->invoice_number,
                ];
            }

        } else {
            // حالة المرتجع: عكس كامل الحركات المالية واللوجستية بالتكلفة والإيراد والقنوات المساعدة

            // أ: عكس قيد المبيعات التجاري (تخفيض الإيرادات ورد الأموال)
            $lines[] = [
                'account_id'      => $incomeAccount->id,
                'sub_ledger_type' => null,
                'sub_ledger_id'   => null,
                'debit'           => $sale->grand_total,
                'credit'          => 0.00,
                'line_notes'      => 'تخفيض إيرادات بموجب مرتجع مبيعات رقم: ' . $sale->invoice_number,
            ];

            if ($sale->payment_type === 'cash') {
                $lines[] = [
                    'account_id'      => $treasuryAccount->id,
                    'sub_ledger_type' => Treasury::class,
                    'sub_ledger_id'   => $sale->treasury_id,
                    'debit'           => 0.00,
                    'credit'          => $sale->grand_total,
                    'line_notes'      => 'رد مبلغ نقدي لعميل بموجب مرتجع رقم: ' . $sale->invoice_number,
                ];
            } elseif ($sale->payment_type === 'card') {
                $lines[] = [
                    'account_id'      => $bankAccount->id,
                    'sub_ledger_type' => Bank::class,
                    'sub_ledger_id'   => $sale->bank_id,
                    'debit'           => 0.00,
                    'credit'          => $sale->grand_total,
                    'line_notes'      => 'رد عبر الحساب البنكي/الشبكة لعميل بموجب مرتجع رقم: ' . $sale->invoice_number,
                ];
            } else { // credit
                $lines[] = [
                    'account_id'      => $customerAccount->id,
                    'sub_ledger_type' => Customer::class,
                    'sub_ledger_id'   => $sale->customer_id,
                    'debit'           => 0.00,
                    'credit'          => $sale->grand_total,
                    'line_notes'      => 'تخفيض مديونية عميل بموجب مرتجع مبيعات رقم: ' . $sale->invoice_number,
                ];
            }

            // ب: عكس قيد التكلفة المرتجعة وإعادة البضاعة للـ Sub-ledger الخاص بالمستودع المختار
            if ($totalInvoiceCost > 0) {
                $lines[] = [
                    'account_id'      => $inventoryAccount->id,
                    'sub_ledger_type' => Store::class,
                    'sub_ledger_id'   => $sale->store_id,
                    'debit'           => $totalInvoiceCost,
                    'credit'          => 0.00,
                    'line_notes'      => 'إعادة إدخال قيمة البضاعة المرتجعة للمستودعات بموجب مستند رقم: ' . $sale->invoice_number,
                ];
                $lines[] = [
                    'account_id'      => $cogsAccount->id,
                    'sub_ledger_type' => null,
                    'sub_ledger_id'   => null,
                    'debit'           => 0.00,
                    'credit'          => $totalInvoiceCost,
                    'line_notes'      => 'تخفيض مصروف تكلفة البضاعة المباعة بالمرتجع رقم: ' . $sale->invoice_number,
                ];
            }

            // عكس وإلغاء عمولة المصمم في حال مرتجع المبيعات
            if ($sale->design_commission > 0 && $sale->designer_id) {
                $lines[] = [
                    'account_id'      => $designersLedgerAccount->id,
                    'sub_ledger_type' => \App\Models\User::class,
                    'sub_ledger_id'   => $sale->designer_id,
                    'debit'           => $sale->design_commission,
                    'credit'          => 0.00,
                    'line_notes'      => 'تخفيض واستقطاع عمولة المصمم نظير مرتجع مبيعات رقم: ' . $sale->invoice_number,
                ];
                $lines[] = [
                    'account_id'      => $designExpenseAccount->id,
                    'sub_ledger_type' => null,
                    'sub_ledger_id'   => null,
                    'debit'           => 0.00,
                    'credit'          => $sale->design_commission,
                    'line_notes'      => 'تخفيض مصروف عمولات التصميم بالمرتجع رقم: ' . $sale->invoice_number,
                ];
            }
        }

        return $this->journalService->createEntry([
            'entry_number' => $sale->invoice_number,
            'entry_date'   => Carbon::parse($sale->invoice_date)->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => $sale->notes ?? 'قيد تلقائي مزدوج ناتج عن حركة مبيعات كاشير المحدثة',
            'user_id'      => $sale->user_id,
            'lines'        => $lines
        ]);
    }

    /**
     * التبديل الذري (Atomic Swap) للخامات بواسطة الفني مع الملاءمة الذكية للوحدات والمخزن وتحديث الحالة التشغيلية للورشة
     */
    public function swapRawMaterials(\App\Models\Sale $sale, array $itemsData, string $productionStatus): \App\Models\Sale
    {
        return DB::transaction(function () use ($sale, $itemsData, $productionStatus) {

            // 1. مسح وتصفير كافة الحركات المخزنية القديمة
            $this->stockService->clearDocumentMovements($sale->invoice_number);

            // 2. تدمير القيد المالي القديم لعزل تكلفة البضاعة السابقة
            if ($sale->journal_entry_id) {
                $oldEntry = \App\Models\JournalEntry::find($sale->journal_entry_id);
                if ($oldEntry) {
                    $this->journalService->deleteEntry($oldEntry);
                    $oldEntry->forceDelete();
                }
            }

            // 3. مطابقة الوحدة القياسية للصنف الجديد وتحديث الحقلين معاً
            foreach ($itemsData as $swapItem) {
                $saleItem = $sale->items()->find($swapItem['sale_item_id']);

                if ($saleItem) {
                    $oldItemUnit = DB::table('item_units')->where('id', $saleItem->item_unit_id)->first();

                    if ($oldItemUnit) {
                        $newItemUnit = DB::table('item_units')
                            ->where('item_id', $swapItem['item_id'])
                            ->where('unit_id', $oldItemUnit->unit_id)
                            ->first();

                        if ($newItemUnit) {
                            $saleItem->update([
                                'item_id'      => $swapItem['item_id'],
                                'item_unit_id' => $newItemUnit->id
                            ]);
                        } else {
                            $fallbackUnit = DB::table('item_units')->where('item_id', $swapItem['item_id'])->first();
                            $saleItem->update([
                                'item_id'      => $swapItem['item_id'],
                                'item_unit_id' => $fallbackUnit?->id ?? $saleItem->item_unit_id
                            ]);
                        }
                    }
                }
            }

            // 4. إعادة الدوران على أسطر الفاتورة لخصم الخامات الجديدة بالمساحة المربعة الفعليه (طول × عرض × كمية)
            foreach ($sale->items()->with(['itemUnit.unit', 'item'])->get() as $saleItem) {
                $calculatedQty = $saleItem->item->is_dimensional
                    ? ((float) $saleItem->length * (float) $saleItem->width * (float) $saleItem->quantity)
                    : (float) $saleItem->quantity;

                $qty = $sale->invoice_type === 'sale' ? -$calculatedQty : $calculatedQty;
                $unitName = $saleItem->itemUnit->unit->name ?? 'حبة';
                $unitFactor = (float) $saleItem->itemUnit->conversion_factor;

                $this->stockService->recordMovement(
                    $saleItem->item_id,
                    $sale->store_id,
                    $saleItem->item_unit_id,
                    $sale->invoice_type === 'sale' ? 'sales' : 'adjustment',
                    $sale->invoice_number,
                    $unitName,
                    $qty,
                    $unitFactor,
                    $saleItem->unit_price,
                    $sale->notes
                );
            }

            // 5. إعادة بناء القيد المحاسبي المزدوج بالتكلفة المحدثة
            $newEntry = $this->generateJournalEntry($sale);

            // [تعديل بروتوكول التنفيذ]: حفظ معرف القيد المالي الجديد مع تحديث الحالة التشغيلية المعتمدة من الفني ذرياً
            $sale->update([
                'journal_entry_id'  => $newEntry->id,
                'production_status' => $productionStatus
            ]);

            return $sale->load('items.itemUnit.unit');
        });
    }
}
