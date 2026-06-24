<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    /**
     * تحديد من يستطيع استعراض دليل الوحدات القياسية
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('unit.view');
    }

    /**
     * تحديد من يستطيع عرض تفاصيل وحدة معينة
     */
    public function view(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('unit.view');
    }

    /**
     * تحديد من يستطيع إنشاء وحدة قياسية جديدة
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('unit.create');
    }

    /**
     * تحديد من يستطيع تعديل بيانات وحدة قائمة
     */
    public function update(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('unit.update');
    }

    /**
     * تحديد من يستطيع حذف وحدة من الدليل
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('unit.delete');
    }
}
