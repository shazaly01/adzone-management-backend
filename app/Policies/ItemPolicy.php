<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    /**
 * Determine whether the user can view any models.
 */
public function viewAny(User $user): bool
{
    // 🌟 [التحديث البرمجي]: السماح بالعبور لمن يملك صلاحية الأصناف أو صلاحية تبديل خامات الورشة
    return $user->hasPermissionTo('item.view') || $user->hasPermissionTo('sale.swap_raw_materials');
}

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Item $item): bool
    {
        return $user->hasPermissionTo('item.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('item.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Item $item): bool
    {
        return $user->hasPermissionTo('item.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Item $item): bool
    {
        // حماية أرشيفية ممتدة: فحص الصلاحية القياسية عبر Spatie
        return $user->hasPermissionTo('item.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Item $item): bool
    {
        return $user->hasPermissionTo('item.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Item $item): bool
    {
        return false; // يمنع الحذف النهائي قطعيًا للحفاظ على الحركات المخزنية والمالية للنظام والتقارير
    }
}
