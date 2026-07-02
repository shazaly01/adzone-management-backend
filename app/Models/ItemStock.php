<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemStock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_id',
        'store_id',
        'current_quantity',
        'reorder_level', // [إضافة]: لتسجيل وتحديث حد الطلب للصنف بالمخزن
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_quantity' => 'float',
        'reorder_level'    => 'float', // [إضافة]: لضمان عودة القيمة رقمية عشرية دائماً
    ];

    /**
     * الارتباط بالصنف الأساسي
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * الارتباط بالمخزن المتواجد به الكمية وحد الطلب
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
