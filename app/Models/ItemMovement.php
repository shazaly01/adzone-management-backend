<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'item_unit_id', // تعديل معماري: ربط الحركة بالوحدة المحددة للصنف في المصفوفة
        'store_id',
        'movement_type',
        'document_no',
        'unit_name_used', // حقل أمان: للاحتفاظ باسم الوحدة كـ Snapshot وقت الحركة
        'quantity',
        'unit_factor',    // حقل أمان: للاحتفاظ بمعامل التحويل كـ Snapshot لحماية التقارير القديمة إذا تغير المعامل مستقبلاً
        'base_quantity',
        'cost_price',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'item_id'       => 'integer',
        'item_unit_id'  => 'integer',
        'store_id'      => 'integer',
        'quantity'      => 'float',
        'unit_factor'   => 'float',
        'base_quantity' => 'float',
        'cost_price'    => 'float',
        'user_id'       => 'integer',
    ];

    /**
     * الارتباط بالصنف المتحرك
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * التعديل المعماري: الارتباط بوحدة الصنف المحددة من المصفوفة
     */
    public function itemUnit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class, 'item_unit_id');
    }

    /**
     * الارتباط بالمخزن الذي تمت فيه الحركة
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * الارتباط بالمستخدم منشئ الحركة للأمان والرقابة
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
