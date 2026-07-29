<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'job_title',
        'account_id',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    /**
     * الحساب الحاكم المرتبط بجدول الموظفين في شجرة الحسابات
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * الحركات المالية المتعددة الأشكال (Polymorphic Sub-Ledger Lines) الخاصة بالموظف
     */
    public function journalLines(): MorphMany
    {
        return $this->morphMany(JournalEntryLine::class, 'sub_ledger');
    }
}
