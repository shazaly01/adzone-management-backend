<?php

namespace App\Policies;

use App\Models\Treasury;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TreasuryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('treasury.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Treasury $treasury): bool
    {
        return $user->hasPermissionTo('treasury.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('treasury.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Treasury $treasury): bool
    {
        return $user->hasPermissionTo('treasury.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Treasury $treasury): bool
    {
        // إذا كانت الخزينة مرتبطة بحساب مالي عليه حركات، يفضل تجميدها (is_active = false) بدلاً من الحذف
        // ولكن كـ Policy نتحقق من الصلاحية أولاً مع منع الحذف إذا كان هناك حركات مادية
        if ($treasury->account && $treasury->account->journalLines()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('treasury.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Treasury $treasury): bool
    {
        return $user->hasPermissionTo('treasury.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Treasury $treasury): bool
    {
        return false;
    }
}
