<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'item_id',
        'item_unit_id', // تعديل معماري: الارتباط بمصفوفة وحدات الصنف بدلاً من الوحدة المجردة
        'quantity',
        'unit_cost',
        'subtotal',
        'discount_amount',
        'grand_total',
    ];

    protected $casts = [
        'purchase_id'  => 'integer',
        'item_id'      => 'integer',
        'item_unit_id' => 'integer',
        'quantity'     => 'float',
        'unit_cost'    => 'float',
        'subtotal'     => 'float',
        'discount_amount' => 'float',
        'grand_total'  => 'float',
    ];

    /**
     * ارتباط سطر التفاصيل برأس الفاتورة الأساسي
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    /**
     * ارتباط السطر بالصنف المحدد من دليل الأصناف
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * التعديل المعماري: ارتباط السطر بالوحدة المحددة للصنف (حبة، كرتون...)
     * والتي تحمل معامل التحويل والتكلفة الخاصة بها
     */
    public function itemUnit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class, 'item_unit_id');
    }
}
