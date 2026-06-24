<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'item_unit_id', // تعديل معماري: الارتباط بمصفوفة وحدات الصنف بدلاً من الوحدة المجردة
        'book_quantity',
        'physical_quantity',
        'quantity_difference',
        'unit_cost',
    ];

    protected $casts = [
        'stock_adjustment_id' => 'integer',
        'item_id'             => 'integer',
        'item_unit_id'        => 'integer',
        'book_quantity'       => 'float',
        'physical_quantity'   => 'float',
        'quantity_difference' => 'float',
        'unit_cost'           => 'float',
    ];

    /**
     * رأس مستند التسوية التابع له هذا السطر
     */
    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    /**
     * الصنف المتأثر بالحركة الجردية
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * التعديل المعماري: الارتباط بوحدة الصنف المحددة في الجرد من المصفوفة لحساب الفروقات بناءً على معامل التحويل
     */
    public function itemUnit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class, 'item_unit_id');
    }
}
