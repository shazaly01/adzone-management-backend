<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * تحديد من يستطيع استعراض قائمة فواتير المبيعات والمرتجع
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sale.view');
    }

    /**
     * تحديد من يستطيع عرض تفاصيل فاتورة مبيعات معينة
     */
    public function view(User $user, Sale $sale): bool
    {
        return $user->hasPermissionTo('sale.view');
    }

    /**
     * تحديد من يستطيع إنشاء فاتورة مبيعات أو مرتجع جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sale.create');
    }

    /**
     * تحديد من يستطيع تعديل الفاتورة فوراً بناءً على امتلاك الصلاحية
     */
    public function update(User $user, Sale $sale): bool
    {
        return $user->hasPermissionTo('sale.update');
    }

    /**
     * تحديد من يستطيع حذف الفاتورة (Soft Delete) من النظام
     */
    public function delete(User $user, Sale $sale): bool
    {
        return $user->hasPermissionTo('sale.delete');
    }


    public function swapRawMaterials(User $user, Sale $sale): bool
    {
        return $user->hasPermissionTo('sale.swap_raw_materials');
    }
}
