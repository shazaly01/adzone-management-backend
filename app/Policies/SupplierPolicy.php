<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('supplier.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('supplier.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('supplier.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('supplier.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        // يمنع حذف المورد إذا كان حسابه المالي يحتوي على تعاملات وقيود سابقة
        if ($supplier->account && $supplier->account->journalLines()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('supplier.delete');
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('supplier.delete');
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return false;
    }
}
