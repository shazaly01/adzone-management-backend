<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\ItemBarcode;
use App\Models\ItemUnitPrice;
use Illuminate\Support\Facades\DB;

class ItemService
{
    /**
     * إنشاء صنف جديد مع كافة مصفوفات الوحدات والأسعار والباركودات التابعة له
     */
    public function create(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            // 1. تسجيل البيانات الأساسية الثابتة للصنف
            $item = Item::create([
                'name'           => $data['name'],
                'item_type'      => $data['item_type'],
                'profit_margin'  => $data['profit_margin'] ?? 0,
                'category_id'    => $data['category_id'] ?? null,
                'category_path'  => $data['category_path'] ?? null,
                'base_unit_id'   => $data['base_unit_id'],
                'is_active'      => $data['is_active'] ?? true,
                'is_dimensional' => $data['is_dimensional'] ?? false, // [إصلاح]: حقن الحقل لتثبيت طبيعة هندسة الصنف عند الإنشاء
            ]);

            // 2. تدوين مصفوفة الوحدات اللانهائية المرسلة عبر الـ Request
            foreach ($data['units'] as $unitData) {
                $itemUnit = ItemUnit::create([
                    'item_id'           => $item->id,
                    'unit_id'           => $unitData['unit_id'],
                    'conversion_factor' => $unitData['conversion_factor'],
                    'cost'              => $unitData['cost'],
                    'price'             => $unitData['price'],
                ]);

                // 3. ربط وتدوين الباركودات المتعددة التابعة لهذه الوحدة
                if (!empty($unitData['barcodes'])) {
                    foreach ($unitData['barcodes'] as $barcode) {
                        ItemBarcode::create([
                            'item_id'      => $item->id,
                            'item_unit_id' => $itemUnit->id,
                            'barcode'      => $barcode,
                        ]);
                    }
                }

                // 4. بناء خلايا مصفوفة أسعار البيع وفئات الخصم لهذه الوحدة
                if (!empty($unitData['prices'])) {
                    foreach ($unitData['prices'] as $priceData) {
                        ItemUnitPrice::create([
                            'item_id'             => $item->id,
                            'item_unit_id'        => $itemUnit->id,
                            'price_list_id'       => $priceData['price_list_id'],
                            'discount_percentage' => $priceData['discount_percentage'] ?? 0,
                            'price'               => $priceData['price'],
                        ]);
                    }
                }
            }

            return $item->load(['units.unit', 'units.barcodes', 'units.prices.priceList', 'baseUnit', 'category']);
        });
    }

    /**
     * تحديث بيانات الصنف ومزامنة مصفوفاته اللانهائية بأمان كامل ومعالجة إحياء الوحدات المحذوفة ناعماً
     */
    public function update(int $id, array $data): Item
    {
        return DB::transaction(function () use ($id, $data) {
            $item = Item::findOrFail($id);

            // 1. تحديث البيانات الإدارية الثابتة للصنف
            $item->update([
                'name'           => $data['name'],
                'item_type'      => $data['item_type'],
                'profit_margin'  => $data['profit_margin'] ?? 0,
                'category_id'    => $data['category_id'] ?? null,
                'category_path'  => $data['category_path'] ?? null,
                'base_unit_id'   => $data['base_unit_id'],
                'is_active'      => $data['is_active'] ?? true,
                'is_dimensional' => $data['is_dimensional'], // [إصلاح جوهري]: حقن الحقل المفقود لحل مشكلة عدم التعديل والمزامنة مع الواجهة
            ]);

            // 2. استخراج مصفوفة معرفات الوحدات القادمة من الـ Request للمقارنة
            $incomingUnitIds = collect($data['units'])->pluck('unit_id')->toArray();

            // 3. التعامل مع الوحدات التي تم حذفها من الواجهة: تحويلها لحذف ناعم (Soft Delete) لحماية مراجع جدول الحركات
            $unitsToRemove = ItemUnit::where('item_id', $item->id)
                ->whereNotIn('unit_id', $incomingUnitIds)
                ->get();

            foreach ($unitsToRemove as $oldUnit) {
                // تنظيف نهائي للباركودات والأسعار التابعة للوحدة المحذوفة لتجنب تصادم قواعد الـ Unique للباركود
                ItemBarcode::where('item_unit_id', $oldUnit->id)->forceDelete();
                ItemUnitPrice::where('item_unit_id', $oldUnit->id)->forceDelete();

                // حذف ناعم للوحدة للحفاظ على معرّفها التاريخي في الجداول الحركية
                $oldUnit->delete();
            }

            // 4. المزامنة الذكية للوحدات المتبقية أو المعاد إحيائها (Bulletproof Upsert Logic)
            foreach ($data['units'] as $unitData) {
                // استخدام withTrashed للبحث في المحذوفات ناعماً وإعادة تفعيلها لمنع تكرار السجلات أو انهيار قاعدة البيانات
                $itemUnit = ItemUnit::withTrashed()->updateOrCreate(
                    [
                        'item_id' => $item->id,
                        'unit_id' => $unitData['unit_id']
                    ],
                    [
                        'conversion_factor' => $unitData['conversion_factor'],
                        'cost'              => $unitData['cost'],
                        'price'             => $unitData['price'],
                        'deleted_at'        => null // إعادة إحياء وتنشيط السجل في حال كان محذوفاً ناعماً تلقائياً
                    ]
                );

                // 5. تنظيف الأسعار والباركودات القديمة التابعة لهذه الوحدة المحددة لإعادة بنائها بأمان
                ItemBarcode::where('item_unit_id', $itemUnit->id)->forceDelete();
                ItemUnitPrice::where('item_unit_id', $itemUnit->id)->forceDelete();

                // 6. إعادة تسجيل الباركودات المحدثة للوحدة الحالية
                if (!empty($unitData['barcodes'])) {
                    foreach ($unitData['barcodes'] as $barcode) {
                        ItemBarcode::create([
                            'item_id'      => $item->id,
                            'item_unit_id' => $itemUnit->id,
                            'barcode'      => $barcode,
                        ]);
                    }
                }

                // 7. إعادة بناء فئات الأسعار المحدثة للوحدة الحالية
                if (!empty($unitData['prices'])) {
                    foreach ($unitData['prices'] as $priceData) {
                        ItemUnitPrice::create([
                            'item_id'             => $item->id,
                            'item_unit_id'        => $itemUnit->id,
                            'price_list_id'       => $priceData['price_list_id'],
                            'discount_percentage' => $priceData['discount_percentage'] ?? 0,
                            'price'               => $priceData['price'],
                        ]);
                    }
                }
            }

            return $item->load(['units.unit', 'units.barcodes', 'units.prices.priceList', 'baseUnit', 'category']);
        });
    }

    /**
     * حذف الصنف وحذف مصفوفاته بشكل أرشيفي ناعم تماشياً مع قواعد حماية التقارير والبيانات المترابطة
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $item = Item::findOrFail($id);

            // مسح نهائي للبيانات الفرعية الحساسة للتكرار (مثل الباركود) لتحريرها للاستخدام مرة أخرى بالنظام
            ItemBarcode::where('item_id', $item->id)->forceDelete();
            ItemUnitPrice::where('item_id', $item->id)->forceDelete();

            // استخدام حذف ناعم (delete) لجدول الوحدات الوسيط بدلاً من الحذف النهائي لحماية تقارير كرت حركة الصنف التاريخية
            ItemUnit::where('item_id', $item->id)->delete();

            return (bool) $item->delete();
        });
    }




    // =========================================================================
    // --- محركات القراءة والبحث والمخزون اللحظي (Stock & Search Engines) ---
    // =========================================================================

  /**
     * جلب قائمة الأصناف المفلترة مع دمج الكمية اللحظية للمخزن المختار إن وجد
     */
    public function searchWithStock(array $filters, ?int $storeId)
    {
        return Item::with(['units.unit', 'units.barcodes', 'units.prices.priceList', 'baseUnit', 'category'])
            ->when($storeId, function ($query) use ($storeId) {
                $query->withSum(['stocks as current_stock' => function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                }], 'current_quantity');
            })
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $query->where(function ($subQuery) use ($filters) {
                    $subQuery->where('name', 'like', '%' . $filters['search'] . '%')
                             ->orWhereHas('barcodes', function ($q) use ($filters) {
                                 $q->where('barcode', $filters['search']);
                             });
                });
            })
            ->when(isset($filters['item_type']), function ($query) use ($filters) {
                $query->where('item_type', $filters['item_type']);
            })
            ->when(isset($filters['category_id']), function ($query) use ($filters) {
                $query->where('category_id', $filters['category_id']);
            })
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', $filters['is_active']);
            })
            ->latest();

    }

    /**
     * جلب خارطة كميات المخزون اللحظية مباشرة من جدول المخزن لسرعة أداء مطلقة وتفادي الـ N+1
     */
    public function refreshStockLevels(int $storeId, array $itemIds): array
    {
        return DB::table('item_stocks')
            ->where('store_id', $storeId)
            ->whereIn('item_id', $itemIds)
            ->pluck('current_quantity', 'item_id')
            ->toArray();
    }
    }
