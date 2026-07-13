<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
     * [تأمين معاملي مطور]: مصفاة مزدوجة تضمن تحويل النص القصير إلى كلاس كامل
     * عند الحفظ (set) لراحة قاعدة البيانات، وعند القراءة (get) لحماية البيانات التاريخية.
     */
    protected function subLedgerType(): Attribute
    {
        $transformer = fn ($value) => match (strtolower(trim($value ?? ''))) {
            'customer', 'client' => \App\Models\Customer::class,
            'supplier'           => \App\Models\Supplier::class,
            'treasury'           => \App\Models\Treasury::class,
            'bank'               => \App\Models\Bank::class,
            'expense'            => \App\Models\Expense::class,
            'user', 'designer'   => \App\Models\User::class,
            default              => $value, // إذا كان كلاس كامل يمر كما هو
        };

        return Attribute::make(
            get: $transformer,
            set: $transformer  // [إصلاح حرج]: لاعتراض الكلمة وتحويلها لكلاس كامل قبل الحفظ في الجدول
        );
    }

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
     * سيقوم لارافيل تلقائياً بالبحث بناءً على sub_ledger_type و sub_ledger_id
     */
    public function subLedger(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'sub_ledger_type', 'sub_ledger_id');
    }
}
