<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    // --- الثوابت: أكواد الحسابات السيادية والتشغيلية الثابتة في الشجرة ---
    public const CODE_TREASURY           = '1101'; // حساب الخزائن الرئيسي
    public const CODE_BANKS              = '1102'; // حساب البنوك الرئيسي
    public const CODE_CUSTOMERS          = '1103'; // حساب العملاء الإجمالي
    public const CODE_INVENTORY          = '1104'; // المخزون الرئيسي
    public const CODE_SUPPLIERS          = '2101'; // حساب الموردين الإجمالي

    // حسابات رأس المال المعتمدة للإقفال الافتتاحي المباشر بناءً على توجيهك المحاسبي
    public const CODE_PAID_CAPITAL       = '2201'; // حساب رأس المال المدفوع والمستثمر نقداً
    public const CODE_ASSET_CAPITAL      = '2202'; // حساب رأس مال الأصول الثابتة والمخزنية الافتتاحية

    public const CODE_INCOME             = '3101'; // حساب الإيرادات التشغيلية
    public const CODE_SURPLUS_INCOME     = '3201'; // أرباح وفروقات جرد المخازن
    public const CODE_EXPENSES           = '4101'; // حساب المصروفات التشغيلية
    public const CODE_SHORTAGE_EXPENSE   = '4102'; // خسائر ومصروفات عجز جرد الأصناف
    public const CODE_COGS               = '4201'; // حساب تكلفة البضاعة والسلع المباعة

    public const CODE_DESIGNERS_LEDGER   = '2103'; // حساب ذمم المصممين المستحقة (التزامات متداولة)
    public const CODE_DESIGN_EXPENSES    = '4103'; // حساب مصروف عمولات تصميم (مصروفات تشغيلية)
    // -----------------------------------------------------------

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'type',
        'nature',
        'opening_balance',
        'current_balance',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    /**
     * علاقة الحساب الأب (لأعلى الشجرة)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id')->withDefault();
    }

    /**
     * علاقة الحسابات الأبناء (لتفريع الشجرة)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * علاقة السطور الماليّة المرتبطة بهذا الحساب في دفتر اليومية
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /**
     * الارتباط بالخزينة الفرعية إن وجدت
     */
    public function treasury(): HasOne
    {
        return $this->hasOne(Treasury::class, 'account_id');
    }

    /**
     * الارتباط بالبنك الفرعي إن وجد
     */
    public function bank(): HasOne
    {
        return $this->hasOne(Bank::class, 'account_id');
    }

    /**
     * الارتباط بالعميل إن وجد
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'account_id');
    }

    /**
     * الارتباط بالمورد إن وجد
     */
    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class, 'account_id');
    }

    /**
     * الارتباط ببند المصروفات إن وجد
     */
    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class, 'account_id');
    }

    /**
     * الارتباط بالمخزن الفرعي إن وجد
     */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class, 'account_id');
    }
}
