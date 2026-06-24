<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    /**
     * تحديد من يستطيع استعراض قائمة الفواتير والمرتجع
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchase.view');
    }

    /**
     * تحديد من يستطيع عرض تفاصيل فاتورة مشتريات معينة
     */
    public function view(User $user, Purchase $purchase): bool
    {
        return $user->hasPermissionTo('purchase.view');
    }

    /**
     * تحديد من يستطيع إنشاء فاتورة أو مرتجع جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchase.create');
    }

    /**
     * تحديد من يستطيع تعديل الفاتورة فوراً بناءً على صلاحيته
     */
    public function update(User $user, Purchase $purchase): bool
    {
        return $user->hasPermissionTo('purchase.update');
    }

    /**
     * تحديد من يستطيع حذف الفاتورة (Soft Delete)
     */
    public function delete(User $user, Purchase $purchase): bool
    {
        return $user->hasPermissionTo('purchase.delete');
    }
}
