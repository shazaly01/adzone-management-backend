<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'credit_limit',
        'account_id',
        'opening_balance', // حقل الرصيد الافتتاحي للتأسيس
        'current_balance', // حقل الرصيد الحالي الفيزيائي لحل مشكلة الأداء N+1
    ];

    protected $casts = [
        'credit_limit'    => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    /**
     * الحساب الرئيسي الإجمالي للعملاء المرتبط بهذا العميل في الشجرة
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * أسطر القيود اليومية المرتبطة بهذا العميل كحساب مساعد
     */
    public function journalLines()
    {
        return $this->morphMany(JournalEntryLine::class, 'sub_ledger');
    }
}
