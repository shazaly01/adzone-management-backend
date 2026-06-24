<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('account.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Account $account): bool
    {
        return $user->hasPermissionTo('account.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('account.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Account $account): bool
    {
        // الحسابات النظامية الثابتة (System) لا يمكن تعديلها لحماية هيكل النظام
        if ($account->type === 'system') {
            return false;
        }

        return $user->hasPermissionTo('account.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Account $account): bool
    {
        // الحسابات النظامية الثابتة أو التي تحتوي على حسابات أبناء لا يمكن حذفها
        if ($account->type === 'system' || $account->children()->exists()) {
            return false;
        }

        // إذا كان الحساب قد سجل حركات مالية سابقة يمنع حذفه منعاً باتاً لسلامة الدفاتر
        if ($account->journalLines()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('account.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Account $account): bool
    {
        return $user->hasPermissionTo('account.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Account $account): bool
    {
        return false; // يمنع الحذف النهائي تماماً في النظام المالي لضمان المرجعية التاريخية
    }
}
