<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    /**
     * تحديد من يستطيع استعراض قائمة مستندات التسوية الجردية
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('stock_adjustment.view');
    }

    /**
     * تحديد من يستطيع عرض تفاصيل مستند تسوية جردية معين
     */
    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->hasPermissionTo('stock_adjustment.view');
    }

    /**
     * تحديد من يستطيع إنشاء مستند تسوية جردية جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('stock_adjustment.create');
    }

    /**
     * تحديد من يستطيع تعديل مستند تسوية جردية قائم بنفس اليوم
     */
    public function update(User $user, StockAdjustment $adjustment): bool
    {
        return $user->hasPermissionTo('stock_adjustment.update');
    }

    /**
     * تحديد من يستطيع حذف وإلغاء مستند التسوية جردياً ومالياً
     */
    public function delete(User $user, StockAdjustment $adjustment): bool
    {
        return $user->hasPermissionTo('stock_adjustment.delete');
    }
}
