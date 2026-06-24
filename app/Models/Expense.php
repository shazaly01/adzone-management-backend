<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'account_id',
        'opening_balance', // حقل الرصيد الافتتاحي المادي الموحد
        'current_balance', // حقل الرصيد الحالي المادي لحماية الأداء
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    /**
     * الحساب الرئيسي الإجمالي للمصروفات المرتبط بهذا البند في الشجرة
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * أسطر القيود اليومية المرتبطة بهذا المصروف كحساب مساعد
     */
    public function journalLines(): MorphMany
    {
        return $this->morphMany(JournalEntryLine::class, 'sub_ledger');
    }
}
