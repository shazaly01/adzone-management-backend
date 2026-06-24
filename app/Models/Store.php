<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'name',
        'location',
        'account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * الحساب المالي المقابل لهذا المخزن في شجرة الحسابات الرئيسية (الأصول المتداولة)
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * جلب جميع القيود المالية التي أثرت على التقييم المالي لهذا المخزن
     */
    public function journalLines()
    {
        return $this->morphMany(JournalEntryLine::class, 'sub_ledger');
    }

    /**
     * حساب القيمة المالية الفعّالة للمخزون داخل هذا المخزن
     * (المخزون أصل، طبيعته مدينة: تزيد قيمته بالمدين وتنقص بالدائن)
     */
    public function getCurrentBalanceAttribute()
    {
        $debit = $this->journalLines()->sum('debit');
        $credit = $this->journalLines()->sum('credit');

        return $debit - $credit;
    }

    /**
     * جلب جميع كميات الأصناف المتوفرة داخل هذا المخزن حالياً (رصيد كميات لوجستي)
     */
    public function stocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ItemStock::class, 'store_id');
    }

    /**
     * جلب جميع الحركات اللوجستية التي تمت داخل هذا المخزن (وارد، منصرف، تحويل)
     */
    public function movements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ItemMovement::class, 'store_id');
    }
}
