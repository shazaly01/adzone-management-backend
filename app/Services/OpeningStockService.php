<?php

namespace App\Services;

use App\Models\OpeningStock;
use App\Models\OpeningStockItem;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Unit;
use App\Services\StockMovementService;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class OpeningStockService
{
    protected StockMovementService $stockService;
    protected JournalEntryService $journalService;

    public function __construct(StockMovementService $stockService, JournalEntryService $journalService)
    {
        $this->stockService = $stockService;
        $this->journalService = $journalService;
    }

    /**
     * إنشاء ومعالجة مستند بضاعة أول مدة جديد وتوليد أثره اللوجستي والمالي فوراً
     */
    public function createOpeningStock(array $data, int $userId): OpeningStock
    {
        return DB::transaction(function () use ($data, $userId) {
            // 1. إنشاء رأس مستند بضاعة أول المدة
            $openingStock = OpeningStock::create([
                'store_id'     => $data['store_id'],
                'opening_date' => $data['opening_date'],
                'notes'        => $data['notes'] ?? null,
                'user_id'      => $userId,
            ]);

            $totalValue = 0.00;
            $items = $data['items'];

            // 2. معالجة وحقن شبكة الأصناف والكميات بناءً على مصفوفة الوحدات اللانهائية
            foreach ($items as $itemData) {
                $item = Item::find($itemData['item_id']);
                if (!$item) {
                    throw new Exception('خطأ لوجستي: الصنف المحدد غير موجود في شجرة الأصناف.');
                }

                // جلب سطر الوحدة المحدد من مصفوفة الأصناف الجديدة لضمان سلامة المعامل ومطابقته
                $itemUnit = ItemUnit::with('unit')
                    ->where('item_id', $item->id)
                    ->where('id', $itemData['item_unit_id'])
                    ->firstOrFail();

                $unitFactor = (float) $itemUnit->conversion_factor;
                $unitName = $itemUnit->unit ? $itemUnit->unit->name : 'حبة';

                $qty = (float) $itemData['quantity'];
                $cost = (float) $itemData['unit_cost'];
                $lineSubtotal = $qty * $cost;
                $totalValue += $lineSubtotal;

                // حقن السطر بالربط مع معرف مصفوفة وحدات الصنف الجديد
                $openingStock->items()->create([
                    'item_id'      => $item->id,
                    'item_unit_id' => $itemUnit->id,
                    'quantity'     => $qty,
                    'unit_cost'    => $cost,
                    'subtotal'     => $lineSubtotal,
                ]);

                // تحديث تكلفة المنتج في جدول مصفوفة الوحدات إذا كانت صفراً
                if ((float) $itemUnit->cost == 0) {
                    $itemUnit->update(['cost' => $cost]);
                }

                // تفويض الحركة المخزنية بمعرف المصفوفة وعامل التحويل الديناميكي
                $this->stockService->recordMovement(
                    $item->id,
                    $data['store_id'],
                    $itemUnit->id,
                    'opening_stock',
                    $openingStock->opening_number,
                    $unitName,
                    $qty,
                    $unitFactor,
                    $cost,
                    $openingStock->notes ?? 'حركة إدخال أرصدة بضاعة أول المدة التأسيسية',
                    $userId
                );
            }

            // 3. توليد وتثبيت القيد المحاسبي المتوازن لـ بضاعة أول المدة
            $journalEntry = $this->generateOpeningStockJournal($openingStock, $totalValue, $userId);
            $openingStock->update(['journal_entry_id' => $journalEntry->id]);

            return $openingStock->load(['items.item', 'items.itemUnit.unit', 'store', 'user']);
        });
    }

    /**
     * تعديل مستند بضاعة أول مدة قائم مع تصفية الأثر القديم وإعادة بنائه بالكامل
     */
    public function updateOpeningStock(OpeningStock $openingStock, array $data, int $userId): OpeningStock
    {
        return DB::transaction(function () use ($openingStock, $data, $userId) {
            // 1. تصفية ومسح أسطر الحركات المخزنية القديمة بالكامل
            $this->stockService->clearDocumentMovements($openingStock->opening_number);

            // 2. معالجة القيد المالي القديم بحسم هندسي صلب لمنع تعليق المفتاح الفريد لقاعدة البيانات
            if ($openingStock->journal_entry_id) {
                $oldEntry = JournalEntry::find($openingStock->journal_entry_id);
                if ($oldEntry) {
                    // أ: استدعاء الخدمة لعكس وتصفية أرصدة الحسابات التفصيلية أولاً حماية للمنظومة المحاسبية
                    $this->journalService->deleteEntry($oldEntry);

                    // ب: الطرد الفيزيائي النهائي (Force Delete) لمنع حدوث خطأ UNIQUE constraint failed عند الحقن الجديد
                    $oldEntry->forceDelete();
                }
            }

            // 3. تصفية أسطر المستند الجاري القديمة
            $openingStock->items()->delete();

            // 4. تحديث بيانات رأس المستند
            $openingStock->update([
                'store_id'     => $data['store_id'],
                'opening_date' => $data['opening_date'],
                'notes'        => $data['notes'] ?? null,
                'user_id'      => $userId,
            ]);

            $totalValue = 0.00;
            $items = $data['items'];

            // 5. إعادة بناء وضخ البيانات الجديدة حركياً ومخزنياً بناءً على مصفوفة الوحدات اللانهائية
            foreach ($items as $itemData) {
                $item = Item::find($itemData['item_id']);
                if (!$item) {
                    throw new Exception('خطأ لوجستي: الصنف المحدد غير موجود في شجرة الأصناف.');
                }

                // جلب سطر الوحدة المحدد من مصفوفة الأصناف الجديدة لضمان سلامة المعامل ومطابقته
                $itemUnit = ItemUnit::with('unit')
                    ->where('item_id', $item->id)
                    ->where('id', $itemData['item_unit_id'])
                    ->firstOrFail();

                $unitFactor = (float) $itemUnit->conversion_factor;
                $unitName = $itemUnit->unit ? $itemUnit->unit->name : 'حبة';

                $qty = (float) $itemData['quantity'];
                $cost = (float) $itemData['unit_cost'];
                $lineSubtotal = $qty * $cost;
                $totalValue += $lineSubtotal;

                // حقن السطر بالربط مع معرف مصفوفة وحدات الصنف الجديد
                $openingStock->items()->create([
                    'item_id'      => $item->id,
                    'item_unit_id' => $itemUnit->id,
                    'quantity'     => $qty,
                    'unit_cost'    => $cost,
                    'subtotal'     => $lineSubtotal,
                ]);

                // تفويض الحركة المخزنية بمعرف المصفوفة وعامل التحويل الديناميكي
                $this->stockService->recordMovement(
                    $item->id,
                    $data['store_id'],
                    $itemUnit->id,
                    'opening_stock',
                    $openingStock->opening_number,
                    $unitName,
                    $qty,
                    $unitFactor,
                    $cost,
                    $openingStock->notes ?? 'إعادة بناء حركات أرصدة بضاعة أول المدة',
                    $userId
                );
            }

            // 6. إعادة توليد القيد المحاسبي المحدث وتثبيته بنظافة مطلقة بعد تفريغ الكود الفريد
            $journalEntry = $this->generateOpeningStockJournal($openingStock, $totalValue, $userId);
            $openingStock->update(['journal_entry_id' => $journalEntry->id]);

            return $openingStock->refresh()->load(['items.item', 'items.itemUnit.unit', 'store', 'user']);
        });
    }

    /**
     * حذف مستند بضاعة أول مدة ناعماً وعكس كامل حركاته المخزنية وقيده المالي
     */
    public function deleteOpeningStock(OpeningStock $openingStock): void
    {
        DB::transaction(function () use ($openingStock) {
            $this->stockService->clearDocumentMovements($openingStock->opening_number);

            if ($openingStock->journal_entry_id) {
                $oldEntry = JournalEntry::find($openingStock->journal_entry_id);
                if ($oldEntry) {
                    $this->journalService->deleteEntry($oldEntry);
                }
            }

            $openingStock->delete();
        });
    }

    /**
     * محرك صياغة وتوجيه قيد بضاعة أول المدة المتوازن
     */
    private function generateOpeningStockJournal(OpeningStock $openingStock, float $totalValue, int $userId): JournalEntry
    {
        if ($totalValue <= 0) {
            throw new Exception('فشل التوجيه المحاسبي: لا يمكن توليد قيد مالي لمستند قيمته الإجمالية صفر.');
        }

        $inventoryAccount = Account::where('code', Account::CODE_INVENTORY)->firstOrFail();
        $assetCapitalAccount = Account::where('code', '2202')->firstOrFail();

        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'debit'      => $totalValue,
                'credit'     => 0.00,
                'line_notes' => 'قيد مدين لإثبات قيمة بضاعة أول المدة الواردة للمستودع بموجب مستند: ' . $openingStock->opening_number,
            ],
            [
                'account_id' => $assetCapitalAccount->id,
                'debit'      => 0.00,
                'credit'     => $totalValue,
                'line_notes' => 'قيد دائن لحساب رأس مال الأصول المخزنية التأسيسية بموجب مستند: ' . $openingStock->opening_number,
            ]
        ];

        return $this->journalService->createEntry([
            'entry_number' => $openingStock->opening_number,
            'entry_date'   => Carbon::parse($openingStock->opening_date)->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => $openingStock->notes ?? 'قيد تأسيسي تلقائي متوازن ناتج عن مستند بضاعة أول المدة',
            'user_id'      => $userId,
            'lines'        => $lines
        ]);
    }
}
