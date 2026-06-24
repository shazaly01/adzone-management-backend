<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * علاقة فئة السعر بمصفوفة أسعار وحدات الأصناف
     * جلب كافة الأسعار المدرجة تحت هذه الفئة
     */
    public function unitPrices(): HasMany
    {
        return $this->hasMany(ItemUnitPrice::class, 'price_list_id');
    }
}
