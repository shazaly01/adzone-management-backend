<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('store.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Store $store): bool
    {
        return $user->hasPermissionTo('store.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('store.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Store $store): bool
    {
        return $user->hasPermissionTo('store.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Store $store): bool
    {
        // قيود الأمان المالي: يمنع حذف المخزن إذا كان حسابه المرتبط في الشجرة يحتوي على أي قيود أو حركات مالية
        if ($store->account && $store->account->journalLines()->exists()) {
            return false;
        }

        return $user->hasPermissionTo('store.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Store $store): bool
    {
        return $user->hasPermissionTo('store.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Store $store): bool
    {
        return false; // يمنع الحذف النهائي من قاعدة البيانات للحفاظ على الأرشيف التاريخي للنظام
    }
}
