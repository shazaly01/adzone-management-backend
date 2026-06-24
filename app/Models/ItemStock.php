<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemStock extends Model
{
    protected $fillable = [
        'item_id',
        'store_id',
        'current_quantity',
    ];

    protected $casts = [
        'current_quantity' => 'float',
    ];

    /**
     * الارتباط بالصنف الأساسي
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * الارتباط بالمخزن المتواجد به الكمية
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
