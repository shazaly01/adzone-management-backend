<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntryLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'line_notes',
        'sub_ledger_type',
        'sub_ledger_id',
    ];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    /**
     * رأس القيد أو السند التابع له هذا السطر تفصيلياً
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * الحساب المتأثر مباشرة في شجرة الحسابات بهذا السطر المالي
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * علاقة مرنة (Polymorphic) لجلب الكيان الفرعي المرتبط (بنك، عميل، مورد، إلخ)
     * سيقوم لارافيل تلقائياً بالبحث بناءً على الاسم الكامل للكلاس المخزن بالـ DB
     */
    public function subLedger(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'sub_ledger_type', 'sub_ledger_id');
    }
}
