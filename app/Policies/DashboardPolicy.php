<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardPolicy
{
    use HandlesAuthorization;

    /**
     * التحقق من امتلاك المستخدم لصلاحية استعراض البيانات والإحصائيات الحساسة للمدير
     * تم توحيد اسم الدالة واسم الصلاحية المفردة لتتطابق مع الفحص المركزي في الـ Controller
     */
    public function manager(User $user): bool
    {
        return $user->hasPermissionTo('dashboard.manager');
    }
}
