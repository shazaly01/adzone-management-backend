<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'name',
        'short_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * جلب كافة الأصناف التي تستخدم هذه الوحدة كـ "وحدة أساسية أولى"
     */
    public function itemsAsUnit1(): HasMany
    {
        return $this->hasMany(Item::class, 'unit1_id');
    }

    /**
     * جلب كافة الأصناف التي تستخدم هذه الوحدة كـ "وحدة فرعية ثانية"
     */
    public function itemsAsUnit2(): HasMany
    {
        return $this->hasMany(Item::class, 'unit2_id');
    }

    /**
     * جلب كافة الأصناف التي تستخدم هذه الوحدة كـ "وحدة فرعية ثالثة"
     */
    public function itemsAsUnit3(): HasMany
    {
        return $this->hasMany(Item::class, 'unit3_id');
    }
}
