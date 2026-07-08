<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'voucher_type',
        'voucher_sequence',
        'voucher_number',
        'account_id',
        'sub_ledger_type',
        'sub_ledger_id',
        'payment_method',
        'fund_account_id',
        'treasury_id',      // [إصلاح معماري]: لربط السند بالخزنة التحليلية كحساب مساعد
        'bank_id',          // [إصلاح معماري]: لربط السند بالبنك التحليلي كحساب مساعد
        'amount',
        'voucher_date',
        'notes',
        'user_id',
        'journal_entry_id',
    ];

    protected $casts = [
        'voucher_sequence' => 'integer',
        'amount'           => 'float',
        'voucher_date'     => 'datetime',
    ];

    /**
     * بوت مدمج لتوليد المتسلسلات والرموز المحمية آلياً عند الإنشاء الفعلي
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($voucher) {
            // التعديل: استخدام withTrashed() لضمان احتساب السندات المحذوفة ناعماً وتجنب تكرار الأرقام
            $lastSequence = self::withTrashed()
                ->where('voucher_type', $voucher->voucher_type)
                ->max('voucher_sequence');

            $nextSequence = $lastSequence ? $lastSequence + 1 : 1;
            $voucher->voucher_sequence = $nextSequence;

            // تحديد البادئة: PAY- لسندات الصرف، REC- لسندات القبض
            $prefix = $voucher->voucher_type === 'payment' ? 'PAY-' : 'REC-';
            $voucher->voucher_number = $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * ارتباط السند بالحساب المالي المستهدف (شجرة الحسابات العامة)
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * ارتباط السند بحساب النقدية الإجمالي (حساب الخزائن أو البنوك التجميعي الثابت)
     */
    public function fundAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fund_account_id');
    }

    /**
     * ارتباط السند بالخزنة الفرعية المحتضنة للحركة النقدية (Sub-ledger)
     */
    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'treasury_id');
    }

    /**
     * ارتباط السند بالبنك الفرعي المحتضن للحركة الإلكترونية (Sub-ledger)
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /**
     * ارتباط مرن لربط السند بكيان الحساب المساعد التابع للأستاذ العام (عميل، مورد، مصروف)
     */
    public function subLedger(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * ارتباط السند بالموظف منشئ المعاملة
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * ارتباط السند بالقيد المالي التلقائي الناتج عنه في دفتر اليومية
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
