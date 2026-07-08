<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'adjustment_sequence',
        'adjustment_number',
        'store_id',
        'adjustment_date',
        'notes',
        'user_id',
        'journal_entry_id',
    ];

    protected $casts = [
        'adjustment_sequence' => 'integer',
        'adjustment_date'     => 'datetime',
    ];

    /**
     * بوت مدمج لتوليد الأرقام والمتسلسلات المحمية تلقائياً عند الإنشاء
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($adjustment) {
            // التعديل: استخدام withTrashed() لتضمين حركات التسوية المحذوفة ناعماً وتفادي تكرار المتسلسلة
            $lastSequence = self::withTrashed()->max('adjustment_sequence');
            $nextSequence = $lastSequence ? $lastSequence + 1 : 1;

            $adjustment->adjustment_sequence = $nextSequence;
            $adjustment->adjustment_number = 'ADJ-' . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * تفاصيل الأصناف والسطور المتأثرة بهذه التسوية الجردية
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }

    /**
     * المستودع الخاضع لعملية التسوية
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * المستخدم أو أمين المخزن الذي قام بالعملية
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * القيد المالي الناتج تلقائياً لتسوية الفروقات وعجز/فائض المخازن
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
