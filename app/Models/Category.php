<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * بوت مدمج (Model Boot) لإدارة حقل الـ path الشجري تلقائياً عند الإنشاء أو التعديل
     */
    protected static function boot()
    {
        parent::boot();

        // عند إنشاء تصنيف جديد
        static::created(function ($category) {
            $category->updatePath();
        });

        // عند تعديل تصنيف قائم (تحديث المسار إذا تغير الأب)
        static::updated(function ($category) {
            if ($category->isDirty('parent_id')) {
                $category->updatePath();
            }
        });
    }

    /**
     * دالة داخلية لتحديث المسار الشجري الذكي (Materialized Path)
     */
    public function updatePath(): void
    {
        if ($this->parent_id) {
            $parent = self::find($this->parent_id);
            $this->path = $parent ? $parent->path . $this->id . '/' : $this->id . '/';
        } else {
            $this->path = $this->id . '/';
        }

        // حفظ المسار بدون تشغيل الأحداث (Events) لتفادي الدوران اللانهائي
        $this->saveQuietly();
    }

    /**
     * علاقة التصنيف الأب (الانتماء)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * علاقة التصنيفات الأبناء المتفرعة (المستوى التالي فقط)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * علاقة الأصناف التابعة لهذا التصنيف مباشرة
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id');
    }
}
