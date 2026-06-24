<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles, HasApiTokens;

    protected string $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'username',
        'email',
        'password',
        'type',
        'store_id',
        'treasury_id',
        'bank_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // =========================================================================
    // --- العلاقات المضافة لربط المستخدم بالمنظومة المالية والمخزنية ---
    // =========================================================================

    /**
     * المخزن المعين للمستخدم بشكل افتراضي
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * الخزينة المعينة للمستخدم بشكل افتراضي
     */
    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'treasury_id');
    }

    /**
     * البنك المعين للمستخدم بشكل افتراضي
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /**
     * جلب جميع القيود اليومية وسندات الصرف والقبض التي أنشأها هذا المستخدم
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'user_id');
    }

    /**
     * جلب جميع الحركات المخزنية (وارد، صادر، تحويل، جرد) التي نفذها هذا المستخدم
     */
    public function itemMovements(): HasMany
    {
        return $this->hasMany(ItemMovement::class, 'user_id');
    }
}
