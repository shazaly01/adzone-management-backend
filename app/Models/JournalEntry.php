<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntry extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'type',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    /**
     * علاقة تفاصيل القيد أو السند (الأسطر المكونة للحركة المادية)
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    /**
     * المستخدم الذي قام بإنشاء وتسجيل هذه الحركة المالية
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }
}
