<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningStock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'opening_number',
        'store_id',
        'opening_date',
        'notes',
        'journal_entry_id',
        'user_id',
    ];

    protected $casts = [
        'opening_date' => 'datetime',
    ];

    /**
     * الحاق التلقائي للرقم المتسلسل عند إنشاء المستند لضمان عدم التكرار والتدخل اليدوي
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($openingStock) {
            if (empty($openingStock->opening_number)) {
                $latest = static::latest('id')->first();
                $sequence = $latest ? ((int) substr($latest->opening_number, -5)) + 1 : 1;
                $openingStock->opening_number = 'OS-' . date('Y') . '-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * علاقة تفاصيل الأصناف والكميات التابعة للمستند
     */
    public function items(): HasMany
    {
        return $this->hasMany(OpeningStockItem::class, 'opening_stock_id');
    }

    /**
     * علاقة المستودع المستهدف بحقن البضاعة الافتتاحية
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id')->withDefault();
    }

    /**
     * علاقة القيد المحاسبي المالي المتوازن المتولد آلياً في الخلفية
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id')->withDefault();
    }

    /**
     * علاقة المستخدم أو أمين المستودع المدخل للبضاعة
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }
}
