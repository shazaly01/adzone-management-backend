<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\Supplier;
use App\Models\Store;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\ItemStock;
use App\Models\Treasury;
use App\Models\Bank;
use App\Services\StockMovementService;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class PurchaseService
{
    protected $stockService;
    protected $journalService;

    public function __construct(StockMovementService $stockService, JournalEntryService $journalService)
    {
        $this->stockService = $stockService;
        $this->journalService = $journalService;
    }

    /**
     * معالجة وحفظ فاتورة مشتريات أو مرتجع جديدة بالكامل
     */
    public function createPurchase(array $data, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {

            $purchaseData = array_merge($data, ['user_id' => $userId]);
            $items = $purchaseData['items'];
            unset($purchaseData['items']);

            $purchase = Purchase::create($purchaseData);

            foreach ($items as $item) {
                // جلب سطر الوحدة المحدد من مصفوفة الأصناف لضمان سلامة معاملات التحويل والتكلفة
                $itemUnit = ItemUnit::with('unit')
                    ->where('item_id', $item['item_id'])
                    ->where('id', $item['item_unit_id'])
                    ->firstOrFail();

                $purchaseItem = $purchase->items()->create([
                    'item_id'         => $item['item_id'],
                    'item_unit_id'    => $itemUnit->id, // ربط السطر بمعرف مصفوفة الوحدات المحدثة
                    'quantity'        => $item['quantity'],
                    'unit_cost'       => $item['unit_cost'],
                    'subtotal'        => $item['subtotal'],
                    'discount_amount' => $item['discount_amount'] ?? 0.00,
                    'grand_total'     => $item['grand_total'],
                ]);

                $qty = $purchase->invoice_type === 'purchase' ? $item['quantity'] : -$item['quantity'];
                $unitName = $itemUnit->unit->name ?? 'حبة';
                $unitFactor = (float) $itemUnit->conversion_factor;

                // 1. محرك احتساب المتوسط المرجح للتكلفة على مستوى الوحدة القياسية الصغرى (Base Unit)
                if ($purchase->invoice_type === 'purchase') {
                    $itemModel = Item::find($item['item_id']);
                    if ($itemModel) {
                        // استدعاء سطر الوحدة الصغرى الافتراضية للصنف من جدول مصفوفة الوحدات
                        $baseUnitRow = ItemUnit::where('item_id', $itemModel->id)
                            ->where('unit_id', $itemModel->base_unit_id)
                            ->first();

                        $currentQty = (float) ItemStock::where('item_id', $item['item_id'])->sum('current_quantity');
                        $oldCost = $baseUnitRow ? (float) $baseUnitRow->cost : 0.00;

                        $unitFactor = (float) ($unitFactor > 0 ? $unitFactor : 1.00);
                        $newQtyBase = (float) $item['quantity'] * $unitFactor;
                        $newCostBase = (float) $item['unit_cost'] / $unitFactor;

                        $totalQty = $currentQty + $newQtyBase;

                        if ($baseUnitRow) {
                            if ($totalQty > 0) {
                                $newWeightedCost = (($currentQty * $oldCost) + ($newQtyBase * $newCostBase)) / $totalQty;
                                $baseUnitRow->update(['cost' => round($newWeightedCost, 4)]);
                            } else {
                                $baseUnitRow->update(['cost' => round($newCostBase, 4)]);
                            }
                        }

                        // تحديث تكلفة شراء هذه الوحدة بالتحديد داخل مصفوفة الوحدات
                        $itemUnit->update(['cost' => (float) $item['unit_cost']]);
                    }
                }

                // 2. تسجيل الحركة المخزنية وتحديث الـ Cache بمعرف المصفوفة الجديد
                $this->stockService->recordMovement(
                    $item['item_id'],
                    $purchase->store_id,
                    $itemUnit->id, // تمرير item_unit_id للتوافق الكامل
                    $purchase->invoice_type === 'purchase' ? 'purchase' : 'adjustment',
                    $purchase->invoice_number,
                    $unitName,
                    $qty,
                    $unitFactor,
                    $item['unit_cost'],
                    $purchase->notes
                );
            }

            // توليد القيد المالي وربط الـ ID الناتج بالفاتورة
            $journalEntry = $this->generateJournalEntry($purchase);
            $purchase->update(['journal_entry_id' => $journalEntry->id]);

            return $purchase->load([
                'items.purchase',
                'items.item.stocks',
                'items.item.units.unit',
                'items.itemUnit.unit'
            ]);
        });
    }

    /**
     * معالجة تعديل وتحديث فاتورة قائمة بشكل فوري وآمن
     */
    public function updatePurchase(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {

            $this->stockService->clearDocumentMovements($purchase->invoice_number);

            // الاعتماد على الرابط المباشر لحذف القيد القديم ومنع تعارض القيود الفريدة بسبب الـ SoftDeletes
            if ($purchase->journal_entry_id) {
                $oldEntry = JournalEntry::find($purchase->journal_entry_id);
                if ($oldEntry) {
                    $this->journalService->deleteEntry($oldEntry);

                    // تنظيف السطر نهائياً من قاعدة البيانات لتفريغ الـ Unique Index وتجنب الكراش 1062
                    $oldEntry->forceDelete();
                }
            }

            $items = $data['items'];
            unset($data['items']);
            $purchase->update($data);

            $purchase->items()->delete();

            foreach ($items as $item) {
                // جلب سطر الوحدة المحدد من مصفوفة الأصناف
                $itemUnit = ItemUnit::with('unit')
                    ->where('item_id', $item['item_id'])
                    ->where('id', $item['item_unit_id'])
                    ->firstOrFail();

                $purchaseItem = $purchase->items()->create([
                    'item_id'         => $item['item_id'],
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => $item['quantity'],
                    'unit_cost'       => $item['unit_cost'],
                    'subtotal'        => $item['subtotal'],
                    'discount_amount' => $item['discount_amount'] ?? 0.00,
                    'grand_total'     => $item['grand_total'],
                ]);

                $qty = $purchase->invoice_type === 'purchase' ? $item['quantity'] : -$item['quantity'];
                $unitName = $itemUnit->unit->name ?? 'حبة';
                $unitFactor = (float) $itemUnit->conversion_factor;

                // 1. الحسبة المالية للمتوسط المرجح بعد تصفية الحركات القديمة وقبل ضخ الجديدة
                if ($purchase->invoice_type === 'purchase') {
                    $itemModel = Item::find($item['item_id']);
                    if ($itemModel) {
                        $baseUnitRow = ItemUnit::where('item_id', $itemModel->id)
                            ->where('unit_id', $itemModel->base_unit_id)
                            ->first();

                        $currentQty = (float) ItemStock::where('item_id', $item['item_id'])->sum('current_quantity');
                        $oldCost = $baseUnitRow ? (float) $baseUnitRow->cost : 0.00;

                        $unitFactor = (float) ($unitFactor > 0 ? $unitFactor : 1.00);
                        $newQtyBase = (float) $item['quantity'] * $unitFactor;
                        $newCostBase = (float) $item['unit_cost'] / $unitFactor;

                        $totalQty = $currentQty + $newQtyBase;

                        if ($baseUnitRow) {
                            if ($totalQty > 0) {
                                $newWeightedCost = (($currentQty * $oldCost) + ($newQtyBase * $newCostBase)) / $totalQty;
                                $baseUnitRow->update(['cost' => round($newWeightedCost, 4)]);
                            } else {
                                $baseUnitRow->update(['cost' => round($newCostBase, 4)]);
                            }
                        }

                        // مزامنة تكلفة الشراء الحالية داخل مصفوفة الوحدات
                        $itemUnit->update(['cost' => (float) $item['unit_cost']]);
                    }
                }

                // 2. تسجيل الحركة الجديدة
                $this->stockService->recordMovement(
                    $item['item_id'],
                    $purchase->store_id,
                    $itemUnit->id,
                    $purchase->invoice_type === 'purchase' ? 'purchase' : 'adjustment',
                    $purchase->invoice_number,
                    $unitName,
                    $qty,
                    $unitFactor,
                    $item['unit_cost'],
                    $purchase->notes
                );
            }

            // إعادة توليد القيد النظيف وتحديث الفاتورة بالمعرف الجديد للربط المالي الصارم
            $newEntry = $this->generateJournalEntry($purchase);
            $purchase->update(['journal_entry_id' => $newEntry->id]);

            return $purchase->load([
                'items.purchase',
                'items.item.stocks',
                'items.item.units.unit',
                'items.itemUnit.unit'
            ]);
        });
    }

    /**
     * حذف أرشيفي للفاتورة وعكس الأثر اللوجستي والمالي بالكامل
     */
    public function deletePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $this->stockService->clearDocumentMovements($purchase->invoice_number);

            if ($purchase->journal_entry_id) {
                $oldEntry = JournalEntry::find($purchase->journal_entry_id);
                if ($oldEntry) {
                    $this->journalService->deleteEntry($oldEntry);
                }
            }

            $purchase->delete();
        });
    }

    /**
     * توليد القيد المحاسبي المتوافق مع حسابات الشجرة التجميعية الثابتة هاردكور والـ Sub-ledgers المساعده
     */
    private function generateJournalEntry(Purchase $purchase): JournalEntry
    {
        $lines = [];

        // جلب الحسابات التجميعية الثابتة هاردكور من الشجرة بالأكواد الرئيسية السيادية
        $inventoryAccount = Account::where('code', Account::CODE_INVENTORY)->firstOrFail();
        $treasuryAccount  = Account::where('code', Account::CODE_TREASURY)->firstOrFail();
        $bankAccount      = Account::where('code', Account::CODE_BANKS)->firstOrFail();
        $supplierAccount  = Account::where('code', Account::CODE_SUPPLIERS)->firstOrFail();

        // توجيه الطرف المالي المقابل ديناميكياً بناءً على طريقة السداد والـ Sub-ledger التابع له بالتطابق مع المبيعات
        $financialAccountId = null;
        $financialSubLedgerType = null;
        $financialSubLedgerId = null;
        $paymentLabel = '';

        if ($purchase->payment_type === 'cash') {
            $financialAccountId = $treasuryAccount->id;
            $financialSubLedgerType = Treasury::class;
            $financialSubLedgerId = $purchase->treasury_id;
            $paymentLabel = 'صرف نقدي من خزينة بموجب فاتورة مشتريات رقم: ';
        } elseif ($purchase->payment_type === 'card') {
            $financialAccountId = $bankAccount->id;
            $financialSubLedgerType = Bank::class;
            $financialSubLedgerId = $purchase->bank_id;
            $paymentLabel = 'صرف بنكي/شبكة بموجب فاتورة مشتريات رقم: ';
        } else { // credit
            $financialAccountId = $supplierAccount->id;
            $financialSubLedgerType = Supplier::class;
            $financialSubLedgerId = $purchase->supplier_id;
            $paymentLabel = 'مستحقات المورد بموجب فاتورة مشتريات رقم: ';
        }

        if ($purchase->invoice_type === 'purchase') {
            // 1. حساب المخزون السلعي التجميعي الرئيسي (أصل) يزيد بالجانب المدين مع ربطه بالمستودع الحالي كـ Sub-ledger
            $lines[] = [
                'account_id'      => $inventoryAccount->id,
                'sub_ledger_type' => Store::class,
                'sub_ledger_id'   => $purchase->store_id,
                'debit'           => $purchase->grand_total,
                'credit'          => 0.00,
                'line_notes'      => 'إثبات بضاعة واردة بموجب فاتورة مشتريات رقم: ' . $purchase->invoice_number,
            ];

            // 2. حساب الطرف المالي التجميعي المقابل يقل أو يزيد التزامه بالجانب الدائن مع تحديد حركته التحليلية كـ Sub-ledger
            $lines[] = [
                'account_id'      => $financialAccountId,
                'sub_ledger_type' => $financialSubLedgerType,
                'sub_ledger_id'   => $financialSubLedgerId,
                'debit'           => 0.00,
                'credit'          => $purchase->grand_total,
                'line_notes'      => $paymentLabel . $purchase->invoice_number,
            ];
        } else {
            // حالة المرتجع: عكس التوجيه المالي والتجميعي للقيد بالكامل بالتوافق والربط مع المبيعات
            // 1. حساب الطرف المالي بالجانب المدين مع الـ Sub-ledger المعتمد تحليلياً
            $lines[] = [
                'account_id'      => $financialAccountId,
                'sub_ledger_type' => $financialSubLedgerType,
                'sub_ledger_id'   => $financialSubLedgerId,
                'debit'           => $purchase->grand_total,
                'credit'          => 0.00,
                'line_notes'      => 'تسوية استرداد مالي/تخفيض التزام بموجب مرتجع مشتريات رقم: ' . $purchase->invoice_number,
            ];

            // 2. حساب المخزون السلعي التجميعي الرئيسي ينخفض بالبضاعة الخارجة بالجانب الدائن مع الـ Sub-ledger للمستودع
            $lines[] = [
                'account_id'      => $inventoryAccount->id,
                'sub_ledger_type' => Store::class,
                'sub_ledger_id'   => $purchase->store_id,
                'debit'           => 0.00,
                'credit'          => $purchase->grand_total,
                'line_notes'      => 'خروج بضاعة بموجب مرتجع مشتريات رقم: ' . $purchase->invoice_number,
            ];
        }

        return $this->journalService->createEntry([
            'entry_number' => $purchase->invoice_number,
            'entry_date'   => Carbon::parse($purchase->invoice_date)->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => $purchase->notes ?? 'قيد تلقائي ناتج عن نظام اللوجستيات والمشتريات المطور',
            'user_id'      => $purchase->user_id,
            'lines'        => $lines
        ]);
    }
}
