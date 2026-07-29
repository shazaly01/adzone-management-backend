<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('employee.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('employee.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('employee.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('employee.update');
    }

    public function delete(User $user, Employee $employee): bool
    {
        // يمنع حذف الموظف إذا كان حسابه المالي أو دفتر أستاذه المساعد مسجلاً عليه حركات قيود سابقة
        if ($employee->journalLines()->exists() || ($employee->account && $employee->account->journalLines()->exists())) {
            return false;
        }

        return $user->hasPermissionTo('employee.delete');
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('employee.delete');
    }

    public function forceDelete(User $user, Employee $employee): bool
    {
        return false;
    }
}
