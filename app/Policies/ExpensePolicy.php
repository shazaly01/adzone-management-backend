<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('expense.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('expense.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('expense.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('expense.update');
    }

    public function delete(User $user, Expense $expense): bool
    {
        // يمنع حذف بند المصروف إذا تم الصرف عليه مسبقاً لحفظ نزاهة التقارير السنوية
        if ($expense->account && $expense->account->journalLines()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('expense.delete');
    }

    public function restore(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('expense.delete');
    }

    public function forceDelete(User $user, Expense $expense): bool
    {
        return false;
    }
}
