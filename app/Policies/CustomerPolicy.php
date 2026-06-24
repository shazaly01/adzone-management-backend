<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('customer.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('customer.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('customer.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('customer.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        // يمنع حذف العميل إذا كان حسابه المالي مسجلاً عليه حركات قيود وسندات سابقة
        if ($customer->account && $customer->account->journalLines()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('customer.delete');
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('customer.delete');
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return false;
    }
}
