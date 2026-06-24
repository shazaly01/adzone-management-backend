<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemUnit extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_id',
        'unit_id',
        'conversion_factor',
        'cost',
        'price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'item_id' => 'integer',
        'unit_id' => 'integer',
        'conversion_factor' => 'float',
        'cost' => 'float',
        'price' => 'float',
    ];

    /**
     * الارتباط بالنموذج الأب (الصنف الرئيسي)
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * الارتباط بالدليل العام المجرد للوحدات لجلب الاسم أو الرمز
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * جلب الباركودات المرتبطة بهذه الوحدة بالتحديد لهذا الصنف
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ItemBarcode::class, 'item_unit_id');
    }

    /**
     * جلب مصفوفة الأسعار وفئات البيع التابعة لهذه الوحدة بالتحديد
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ItemUnitPrice::class, 'item_unit_id');
    }
}
