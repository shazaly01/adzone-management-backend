<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * تحديد من يستطيع استعراض قائمة التصنيفات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('category.view');
    }

    /**
     * تحديد من يستطيع عرض تفاصيل تصنيف معين
     */
    public function view(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('category.view');
    }

    /**
     * تحديد من يستطيع إنشاء تصنيف جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('category.create');
    }

    /**
     * تحديد من يستطيع تعديل تصنيف قائم
     */
    public function update(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('category.update');
    }

    /**
     * تحديد من يستطيع حذف تصنيف (Soft Delete)
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('category.delete');
    }
}
