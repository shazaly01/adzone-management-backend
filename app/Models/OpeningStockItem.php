<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningStockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_stock_id',
        'item_id',
        'item_unit_id', // تعديل معماري: الارتباط بمصفوفة وحدات الصنف بدلاً من الوحدة المجردة
        'quantity',
        'unit_cost',
        'subtotal',
    ];

    protected $casts = [
        'opening_stock_id' => 'integer',
        'item_id'          => 'integer',
        'item_unit_id'     => 'integer',
        'quantity'         => 'float',
        'unit_cost'        => 'float',
        'subtotal'         => 'float',
    ];

    /**
     * :الارتباط برأس مستند بضاعة أول المدة
     */
    public function openingStock(): BelongsTo
    {
        return $this->belongsTo(OpeningStock::class, 'opening_stock_id');
    }

    /**
     * الارتباط بالصنف المخزني المستهدف
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id')->withDefault();
    }

    /**
     * التعديل المعماري: الارتباط بالوحدة اللوجستية المحددة للصنف والمستخدمة في الجرد الافتتاحي
     */
    public function itemUnit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class, 'item_unit_id')->withDefault();
    }
}
