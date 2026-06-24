<?php

namespace App\Services;

use App\Models\ItemMovement;
use App\Models\ItemStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockMovementService
{
    /**
     * تسجيل حركة مخزنية جديدة وتحديث المخزون اللحظي فوراً بالربط مع جدول الوحدات الجديد
     * * @param int $itemId معرف الصنف
     * @param int $storeId معرف المخزن
     * @param int $itemUnitId معرف السطر الرابط لوحدة الصنف من مصفوفة الوحدات
     * @param string $movementType (opening, purchase, sales, transfer_in, transfer_out, adjustment)
     * @param string $documentNo رقم المستند المرجعي (رقم الفاتورة مثلاً)
     * @param string $unitName اسم الوحدة المستخدمة كـ Snapshot (قطعة، ربطة، صندوق)
     * @param float $quantity الكمية (موجبة للوارد، سالبة للصادر)
     * @param float $unitFactor معامل التحويل للوحدة الصغرى كـ Snapshot
     * @param float $costPrice سعر تكلفة الوحدة وقت الحركة
     * @param string|null $notes ملاحظات الحركة
     * @param int|null $userId معرف المستخدم المسؤول عن الحركة (اختياري)
     * @return ItemMovement
     */
    public function recordMovement(
        int $itemId,
        int $storeId,
        int $itemUnitId, // تعديل معماري: استقبال معرف وحدة الصنف بدلاً من الوحدة المجردة
        string $movementType,
        string $documentNo,
        string $unitName,
        float $quantity,
        float $unitFactor,
        float $costPrice,
        ?string $notes = null,
        ?int $userId = null
    ): ItemMovement {
        return DB::transaction(function () use ($itemId, $storeId, $itemUnitId, $movementType, $documentNo, $unitName, $quantity, $unitFactor, $costPrice, $notes, $userId) {

            // 1. احتساب الكمية الصافية الفعلية بالوحدة الصغرى الأساسية لتوحيد الجرد
            $baseQuantity = $quantity * $unitFactor;

            // 2. تسجيل السجل التاريخي للحركة في دفتر اليومية المخزني بالربط مع المصفوفة الجديدة
            $movement = ItemMovement::create([
                'item_id'        => $itemId,
                'item_unit_id'   => $itemUnitId, // تعديل معماري حتمي للتوافق مع جدول item_barcodes و item_units
                'store_id'       => $storeId,
                'movement_type'  => $movementType,
                'document_no'    => $documentNo,
                'unit_name_used' => $unitName,
                'quantity'       => $quantity,
                'unit_factor'    => $unitFactor,
                'base_quantity'  => $baseQuantity,
                'cost_price'     => $costPrice,
                'notes'          => $notes,
                'user_id'        => $userId ?? Auth::id() ?? 1, // حماية التشغيل الآمن للنظام في الـ Queues
            ]);

            // 3. تحديث جدول المخزون اللحظي الفوري الموحد بالوحدة الصغرى (Cache Table)
            $stock = ItemStock::firstOrCreate(
                ['item_id' => $itemId, 'store_id' => $storeId],
                ['current_quantity' => 0.0000]
            );

            // زيادة أو خفض الكمية بناءً على صافي الحركة المضروبة بالمعامل
            $stock->increment('current_quantity', $baseQuantity);

            return $movement;
        });
    }

    /**
     * إلغاء وعكس أثر حركات مخزنية مرتبطة بمستند معين (تمهيداً للتعديل أو الحذف المباشر)
     */
    public function clearDocumentMovements(string $documentNo): void
    {
        DB::transaction(function () use ($documentNo) {
            // جلب كافة الحركات التاريخية المسجلة تحت هذا المستند
            $movements = ItemMovement::where('document_no', $documentNo)->get();

            foreach ($movements as $movement) {
                // عكس الأثر الكمي في جدول المخزون اللحظي الفوري الموحد بالوحدة الصغرى
                $stock = ItemStock::where('item_id', $movement->item_id)
                    ->where('store_id', $movement->store_id)
                    ->first();

                if ($stock) {
                    $stock->decrement('current_quantity', $movement->base_quantity);
                }

                // حذف السجل التاريخي القديم
                $movement->delete();
            }
        });
    }
}
