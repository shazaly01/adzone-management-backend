<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_type',
        'invoice_sequence',
        'invoice_number',
        'parent_id',
        'store_id',
        'treasury_id',      // [تحديث مالي]: إدراج الحقل ماليّاً لقنوات الصرف النقدي فور الحفظ
        'bank_id',          // [تحديث مالي]: إدراج الحقل ماليّاً لقنوات الصرف الإلكتروني (الشبكة)
        'supplier_id',
        'user_id',
        'journal_entry_id', // لربط الفاتورة بقيدها المالي الناتج في اليومية العامة
        'invoice_date',
        'payment_type',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'notes',
    ];

    protected $casts = [
        'invoice_sequence' => 'integer',
        'invoice_date'     => 'datetime',
        'subtotal'         => 'float',
        'discount_amount'  => 'float',
        'tax_amount'       => 'float',
        'grand_total'      => 'float',
    ];

    /**
     * بوت مدمج (Model Boot) لتوليد الرقم التلقائي النقي والمحمي عند الحفظ الفعلي
     */
   protected static function boot()
{
    parent::boot();

    static::creating(function ($purchase) {
        // 1. الحصول على أعلى قيمة مسلسلة من الفواتير غير المحذوفة فقط
        $lastSequence = self::where('invoice_type', $purchase->invoice_type)
            ->whereNull('deleted_at')
            ->max('invoice_sequence');

        $nextSequence = $lastSequence ? $lastSequence + 1 : 1;

        // 2. التحقق الدفاعي: التأكد من أن الرقم المسلسل التالي غير مستخدم حتى في الفواتير المحذوفة ناعماً
        while (self::withTrashed()
            ->where('invoice_type', $purchase->invoice_type)
            ->where('invoice_sequence', $nextSequence)
            ->exists()
        ) {
            $nextSequence++;
        }

        // 3. تعيين القيم الجديدة
        $purchase->invoice_sequence = $nextSequence;
        $prefix = $purchase->invoice_type === 'purchase' ? 'PUR-' : 'PR-';
        $purchase->invoice_number = $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    });
}
    /**
     * ارتباط المرتجع بالفاتورة الأصلية (في حال كان المستند من نوع return)
     */
    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'parent_id');
    }

    /**
     * جلب كافة مستندات المردودات المرتبطة بهذه الفاتورة الأصلية
     */
    public function returns(): HasMany
    {
        return $this->hasMany(Purchase::class, 'parent_id');
    }

    /**
     * ارتباط الفاتورة بالسطور والتفاصيل الخاصة بالأصناف
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }

    /**
     * ارتباط الفاتورة بالمستودع الذي تمت فيه الحركة
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * [ارتباط مالي جديد]: ربط الفاتورة بالخزنة الصارفة للأموال النقدية
     */
    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'treasury_id');
    }

    /**
     * [ارتباط مالي جديد]: ربط الفاتورة بالحساب البنكي الصارف للمشتريات الإلكترونية (الشبكة)
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /**
     * ارتباط الفاتورة بكيان المورد المستقل
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * ارتباط الفاتورة بالموظف منشئ السجل
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * ارتباط الفاتورة بالقيد المحاسبي الناتج عنها في دفتر اليومية
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
