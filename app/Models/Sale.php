<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    // ثوابت الحالات التشغيلية لورشة التنفيذ لتوحيد الكود في النظام
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'invoice_type',
        'invoice_sequence',
        'invoice_number',
        'parent_id',
        'store_id',
        'treasury_id',
        'bank_id',
        'customer_id',
        'user_id',
        'designer_id', // [إضافة]: معرف المصمم المسؤول ماليّاً
        'journal_entry_id',
        'invoice_date',
        'payment_type',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'designer_meter_price', // [إضافة]: سعر المتر المتغير للفاتورة الحالية
        'design_commission',    // [إضافة]: إجمالي عمولة المصمم المحسوبة للفاتورة
        'notes',
        'production_status',    // [إضافة]: الحالة التشغيلية الخاصة بفني الطباعة والورشة
    ];

    protected $casts = [
        'invoice_sequence'     => 'integer',
        'invoice_date'         => 'datetime',
        'subtotal'             => 'float',
        'discount_amount'      => 'float',
        'tax_amount'           => 'float',
        'grand_total'          => 'float',
        'designer_id'          => 'integer',
        'designer_meter_price' => 'float',
        'design_commission'    => 'float',
        'production_status'    => 'string',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            $lastSequence = self::where('invoice_type', $sale->invoice_type)
                ->max('invoice_sequence');

            $nextSequence = $lastSequence ? $lastSequence + 1 : 1;
            $sale->invoice_sequence = $nextSequence;

            $prefix = $sale->invoice_type === 'sale' ? 'INV-' : 'SR-';
            $sale->invoice_number = $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
        });
    }

    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'parent_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(Sale::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'treasury_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * [ارتباط معماري جديد]: ربط الفاتورة بالمصمم المسؤول من جدول المستخدمين
     */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
