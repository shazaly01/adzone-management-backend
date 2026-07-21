<?php

namespace App\Models;

use App\Models\User;
use App\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PendingInvoiceItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Table associated with the model.
     *
     * @var string
     */
    protected $table = 'pending_invoice_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'item_id',
        'raw_text',
        'ai_output',
        'height',
        'width',
        'quantity',
        'price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id'   => 'integer',
        'item_id'   => 'integer',
        'ai_output' => 'array',
        'height'    => 'float',
        'width'     => 'float',
        'quantity'  => 'integer',
        'price'     => 'float',
    ];

    // =========================================================================
    // --- العلاقات (Relationships) ---
    // =========================================================================

    /**
     * ارتباط السطر الصوتي بالمستخدم/الموظف الذي أنشأه
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * ارتباط السطر الصوتي المعلق بالصنف المطابق له في النظام
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
