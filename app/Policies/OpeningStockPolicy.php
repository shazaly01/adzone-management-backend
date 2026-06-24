<?php

namespace App\Policies;

use App\Models\OpeningStock;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OpeningStockPolicy
{
    use HandlesAuthorization;

    /**
     * التحقق من صلاحية استعراض قائمة مستندات بضاعة أول المدة
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('opening_stock.view', 'api');
    }

    /**
     * التحقق من صلاحية عرض تفاصيل مستند معين
     */
    public function view(User $user, OpeningStock $openingStock): bool
    {
        return $user->hasPermissionTo('opening_stock.view', 'api');
    }

    /**
     * التحقق من صلاحية إنشاء مستند بضاعة أول مدة جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('opening_stock.create', 'api');
    }

    /**
     * التحقق من صلاحية تعديل المستند
     */
    public function update(User $user, OpeningStock $openingStock): bool
    {
        return $user->hasPermissionTo('opening_stock.update', 'api');
    }

    /**
     * التحقق من صلاحية الحذف المحمي للمستند
     */
    public function delete(User $user, OpeningStock $openingStock): bool
    {
        return $user->hasPermissionTo('opening_stock.delete', 'api');
    }
}
