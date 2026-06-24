<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUnitPrice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_id',
        'item_unit_id',
        'price_list_id',
        'discount_percentage',
        'price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'item_id' => 'integer',
        'item_unit_id' => 'integer',
        'price_list_id' => 'integer',
        'discount_percentage' => 'float',
        'price' => 'float',
    ];

    /**
     * الارتباط بالنموذج الرئيسي (الصنف)
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * الارتباط بوحدة الصنف المحددة التابع لها هذا السعر والفئة
     */
    public function itemUnit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class, 'item_unit_id');
    }

    /**
     * الارتباط بقائمة فئة السعر (جمهور، جملة، موزعون...)
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }
}
