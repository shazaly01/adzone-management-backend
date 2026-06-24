<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\ItemStock;
use App\Models\Store;
use App\Services\StockMovementService;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class StockAdjustmentService
{
    protected StockMovementService $stockMovementService;
    protected JournalEntryService $journalEntryService;

    public function __construct(
        StockMovementService $stockMovementService,
        JournalEntryService $journalEntryService
    ) {
        $this->stockMovementService = $stockMovementService;
        $this->journalEntryService = $journalEntryService;
    }

   /**
     * إنشاء معالجة تسوية جردية جديدة وتوليد قيودها وحركاتها فوراً (نسخة محسنة الأداء)
     */
    public function createAdjustment(array $data, int $userId): StockAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            // 1. إنشاء رأس مستند التسوية الجردية
            $adjustment = StockAdjustment::create([
                'store_id'        => $data['store_id'],
                'adjustment_date' => $data['adjustment_date'],
                'notes'           => $data['notes'] ?? null,
                'user_id'         => $userId,
            ]);

            $totalShortageValue = 0.00;
            $totalSurplusValue = 0.00;

            // تحسين الأداء: جلب كافة الأصناف والوحدات مسبقاً خارج الحلقة لمنع الـ N+1 Queries
            $itemIds = collect($data['items'])->pluck('item_id')->unique()->toArray();
            $unitIds = collect($data['items'])->pluck('item_unit_id')->unique()->toArray();

            $itemsCollection = Item::whereIn('id', $itemIds)->get()->keyBy('id');
            $itemUnitsCollection = ItemUnit::with('unit')
                ->whereIn('id', $unitIds)
                ->get()
                ->keyBy('id');

            // 2. معالجة السطور والأصناف حركياً ومخزنياً بناءً على الحسابات اللحظية
            foreach ($data['items'] as $itemData) {
                $item = $itemsCollection->get($itemData['item_id']);
                if (!$item) {
                    throw new Exception('خطأ لوجستي: الصنف المحدد غير موجود في النظام.');
                }

                $itemUnit = $itemUnitsCollection->get($itemData['item_unit_id']);
                if (!$itemUnit || $itemUnit->item_id !== $item->id) {
                    throw new Exception('خطأ لوجستي: وحدة الصنف المحددة غير متطابقة.');
                }

                $unitFactor = (float) $itemUnit->conversion_factor;
                $unitName = $itemUnit->unit ? $itemUnit->unit->name : 'حبة';

                // الحماية السيادية: قفل سطر الرصيد الحالي فقط لمنع الـ Race Conditions
                $stock = ItemStock::where('item_id', $item->id)
                    ->where('store_id', $data['store_id'])
                    ->lockForUpdate()
                    ->first();

                // احتساب الكمية الدفترية الفعلية بالوحدة المختارة بناءً على رصيد المستودع الحالي بالوحدة الصغرى
                $baseBookQty = $stock ? (float) $stock->current_quantity : 0.00;
                $bookQty = $unitFactor > 0 ? ($baseBookQty / $unitFactor) : $baseBookQty;

                $physicalQty = (float) $itemData['physical_quantity'];
                $qtyDifference = $physicalQty - $bookQty;
                $unitCost = (float) $itemData['unit_cost'];
                $lineValue = abs($qtyDifference * $unitCost);

                if ($qtyDifference < 0) {
                    $totalShortageValue += $lineValue;
                } else {
                    $totalSurplusValue += $lineValue;
                }

                // حفظ سطر التسوية الجردية تفصيلياً
                $adjustment->items()->create([
                    'item_id'             => $itemData['item_id'],
                    'item_unit_id'        => $itemUnit->id,
                    'book_quantity'       => $bookQty,
                    'physical_quantity'   => $physicalQty,
                    'quantity_difference' => $qtyDifference,
                    'unit_cost'           => $unitCost,
                ]);

                // استدعاء الخدمة لتسجيل الحركة وتحديث جدول المخزون اللحظي الفوري
                $this->stockMovementService->recordMovement(
                    $itemData['item_id'],
                    $data['store_id'],
                    $itemUnit->id,
                    'adjustment',
                    $adjustment->adjustment_number,
                    $unitName,
                    $qtyDifference,
                    $unitFactor,
                    $unitCost,
                    'حركة تسوية تلقائية ناتجة عن مستند رقم: ' . $adjustment->adjustment_number,
                    $userId
                );
            }

            // 3. توليد وتوازن القيد المحاسبي المالي التلقائي
            $journalEntry = $this->generateAdjustmentJournal($adjustment, $totalShortageValue, $totalSurplusValue, $userId);

            if ($journalEntry) {
                $adjustment->update(['journal_entry_id' => $journalEntry->id]);
            }

            return $adjustment->load([
                'items.stockAdjustment',
                'items.item.stocks',
                'items.item.units.unit',
                'items.itemUnit.unit',
                'store',
                'user'
            ]);
        });
    }

   /**
     * تعديل مستند تسوية قائم بنفس اليوم مع حماية كاملة وأداء محسن
     */
    public function updateAdjustment(StockAdjustment $adjustment, array $data, int $userId): StockAdjustment
    {
        if (!Carbon::parse($adjustment->adjustment_date)->isSameDay(Carbon::now())) {
            throw new Exception('لا يمكن تعديل مستندات التسوية الجردية العائدة لأيام سابقة مباشرة لحفظ سلامة الفترات المالية المحاسبية.');
        }

        return DB::transaction(function () use ($adjustment, $data, $userId) {
            // 1. مسح وإزاحة كافة الحركات المخزنية القديمة وعكس الـ Cache كمياً
            $this->stockMovementService->clearDocumentMovements($adjustment->adjustment_number);

            // 2. تدمير وحذف القيد المالي القديم المرتبط بالتسوية
            if ($adjustment->journal_entry_id) {
                $oldEntry = JournalEntry::find($adjustment->journal_entry_id);
                if ($oldEntry) {
                    $this->journalEntryService->deleteEntry($oldEntry);
                    $oldEntry->forceDelete();
                }
            }

            // 3. مسح أسطر تفاصيل الأصناف القديمة داخل مستند التسوية
            $adjustment->items()->delete();

            // 4. تحديث بيانات رأس مستند التسوية الجردية
            $adjustment->update([
                'store_id'        => $data['store_id'],
                'adjustment_date' => $data['adjustment_date'],
                'notes'           => $data['notes'] ?? null,
                'user_id'         => $userId,
            ]);

            $totalShortageValue = 0.00;
            $totalSurplusValue = 0.00;

            // تحسين الأداء: جلب كافة الأصناف والوحدات مسبقاً خارج الحلقة لمنع الـ N+1 Queries عند التحديث
            $itemIds = collect($data['items'])->pluck('item_id')->unique()->toArray();
            $unitIds = collect($data['items'])->pluck('item_unit_id')->unique()->toArray();

            $itemsCollection = Item::whereIn('id', $itemIds)->get()->keyBy('id');
            $itemUnitsCollection = ItemUnit::with('unit')
                ->whereIn('id', $unitIds)
                ->get()
                ->keyBy('id');

            // 5. إعادة بناء الحركات الجردية بالاعتماد الكلي على جلب الرصيد التاريخي الحقيقي
            foreach ($data['items'] as $itemData) {
                $item = $itemsCollection->get($itemData['item_id']);
                if (!$item) {
                    throw new Exception('خطأ لوجستي: الصنف المحدد غير موجود في النظام.');
                }

                $itemUnit = $itemUnitsCollection->get($itemData['item_unit_id']);
                if (!$itemUnit || $itemUnit->item_id !== $item->id) {
                    throw new Exception('خطأ لوجستي: وحدة الصنف المحددة غير متطابقة.');
                }

                $unitFactor = (float) $itemUnit->conversion_factor;
                $unitName = $itemUnit->unit ? $itemUnit->unit->name : 'حبة';

                // الحماية السيادية في التعديل: جلب المخزون اللحظي الفوري المحدث مع قفل السطر
                $stock = ItemStock::where('item_id', $item->id)
                    ->where('store_id', $data['store_id'])
                    ->lockForUpdate()
                    ->first();

                $baseBookQty = $stock ? (float) $stock->current_quantity : 0.00;
                $bookQty = $unitFactor > 0 ? ($baseBookQty / $unitFactor) : $baseBookQty;

                $physicalQty = (float) $itemData['physical_quantity'];
                $qtyDifference = $physicalQty - $bookQty;
                $unitCost = (float) $itemData['unit_cost'];
                $lineValue = abs($qtyDifference * $unitCost);

                if ($qtyDifference < 0) {
                    $totalShortageValue += $lineValue;
                } else {
                    $totalSurplusValue += $lineValue;
                }

                $adjustment->items()->create([
                    'item_id'             => $itemData['item_id'],
                    'item_unit_id'        => $itemUnit->id,
                    'book_quantity'       => $bookQty,
                    'physical_quantity'   => $physicalQty,
                    'quantity_difference' => $qtyDifference,
                    'unit_cost'           => $unitCost,
                ]);

                $this->stockMovementService->recordMovement(
                    $itemData['item_id'],
                    $data['store_id'],
                    $itemUnit->id,
                    'adjustment',
                    $adjustment->adjustment_number,
                    $unitName,
                    $qtyDifference,
                    $unitFactor,
                    $unitCost,
                    'إعادة بناء حركة تسوية تلقائية ناتجة عن مستند رقم: ' . $adjustment->adjustment_number,
                    $userId
                );
            }

            // 6. إعادة توليد وتثبيت القيد المحاسبي المتوازن المحدث بالكامل
            $journalEntry = $this->generateAdjustmentJournal($adjustment, $totalShortageValue, $totalSurplusValue, $userId);

            if ($journalEntry) {
                $adjustment->update(['journal_entry_id' => $journalEntry->id]);
            }

            return $adjustment->refresh()->load([
                'items.stockAdjustment',
                'items.item.stocks',
                'items.item.units.unit',
                'items.itemUnit.unit',
                'store',
                'user'
            ]);
        });
    }


    /**
     * حذف مستند تسوية جردية ناعماً وعكس كامل أثره المالي والمخزني بالتتابع الحتمي
     */
    public function deleteAdjustment(StockAdjustment $adjustment): void
    {
        DB::transaction(function () use ($adjustment) {
            // 1. مسح وإزاحة الأثر الكمي تماماً وإعادة المخازن لحالتها السابقة
            $this->stockMovementService->clearDocumentMovements($adjustment->adjustment_number);

            // 2. حذف وإلغاء أسطر القيد المالي المرتبط من الدفاتر الحسابية
            if ($adjustment->journal_entry_id) {
                $oldEntry = JournalEntry::find($adjustment->journal_entry_id);
                if ($oldEntry) {
                    $this->journalEntryService->deleteEntry($oldEntry);
                }
            }

            // 3. عمل الحذف الناعم لرأس مستند التسوية
            $adjustment->delete();
        });
    }

    /**
     * محرك توليد وتوجيه قيود التسويات آلياً لشجرة الحسابات التجميعية الثابتة والـ Sub-ledgers للمخازن
     */
    private function generateAdjustmentJournal(StockAdjustment $adjustment, float $shortage, float $surplus, int $userId): ?JournalEntry
    {
        if ($shortage == 0 && $surplus == 0) {
            return null;
        }

        $inventoryAccount       = Account::where('code', Account::CODE_INVENTORY)->firstOrFail();
        $shortageExpenseAccount = Account::where('code', Account::CODE_SHORTAGE_EXPENSE)->firstOrFail();
        $surplusIncomeAccount   = Account::where('code', Account::CODE_SURPLUS_INCOME)->firstOrFail();

        if (!$inventoryAccount || !$shortageExpenseAccount || !$surplusIncomeAccount) {
            throw new Exception('فشل التوجيه المحاسبي: يجب تهيئة أكواد الحسابات السيادية للمخازن والمصروفات والإيرادات أولاً.');
        }

        $lines = [];

        if ($shortage > 0) {
            // أ. حساب مصروف خسائر وعجز الجرد (مدين بالخسارة لزيادة المصاريف)
            $lines[] = [
                'account_id'      => $shortageExpenseAccount->id,
                'sub_ledger_type' => null,
                'sub_ledger_id'   => null,
                'debit'           => $shortage,
                'credit'          => 0.00,
                'line_notes'      => 'إثبات مصروف خسائر عجز الجرد الفعلي لمستند رقم: ' . $adjustment->adjustment_number,
            ];

            // ب. حساب المخزون التجميعي ينخفض (دائن بالبضاعة الخارجة) مع ربط المستودع كـ Sub-ledger
            $lines[] = [
                'account_id'      => $inventoryAccount->id,
                'sub_ledger_type' => Store::class,
                'sub_ledger_id'   => $adjustment->store_id,
                'debit'           => 0.00,
                'credit'          => $shortage,
                'line_notes'      => 'تخفيض قيمة المخازن بموجب عجز جرد مستند: ' . $adjustment->adjustment_number,
            ];
        }

        if ($surplus > 0) {
            // أ. حساب المخزون التجميعي يرتفع (مدين بالبضاعة الفائضة الواردة) مع ربط المستودع كـ Sub-ledger
            $lines[] = [
                'account_id'      => $inventoryAccount->id,
                'sub_ledger_type' => Store::class,
                'sub_ledger_id'   => $adjustment->store_id,
                'debit'           => $surplus,
                'credit'          => 0.00,
                'line_notes'      => 'زيادة قيمة وتقييم المخازن بموجب فائض جرد مستند: ' . $adjustment->adjustment_number,
            ];

            // ب. حساب أرباح وفروقات جرد المخازن (دائن بالإيراد الفائض الناتج)
            $lines[] = [
                'account_id'      => $surplusIncomeAccount->id,
                'sub_ledger_type' => null,
                'sub_ledger_id'   => null,
                'debit'           => 0.00,
                'credit'          => $surplus,
                'line_notes'      => 'إثبات أرباح وفائض الجرد الفعلي لمستند رقم: ' . $adjustment->adjustment_number,
            ];
        }

        return $this->journalEntryService->createEntry([
            'entry_number' => $adjustment->adjustment_number,
            'entry_date'   => Carbon::parse($adjustment->adjustment_date)->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => $adjustment->notes ?? 'قيد تلقائي ناتج عن تسوية الفروقات الجردية للمستودع المطور',
            'user_id'      => $userId,
            'lines'        => $lines
        ]);
    }
}
