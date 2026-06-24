<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
   /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // [تعديل أمني مالي]: السماح للكاشير بجلب قائمة المصممين فقط لتغذية شاشة المبيعات دون منحه صلاحيات الإدارة الشاملة
        if (request()->get('type') === 'designer' && ($user->can('sale.create') || $user->can('sale.view'))) {
            return true;
        }

        return $user->can('user.view');
    }
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('user.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // لا يمكن لأي شخص تعديل مستخدم "Super Admin" إلا إذا كان هو نفسه
        if ($model->hasRole('Super Admin') && $user->id !== $model->id) {
            return false;
        }

        // لا يمكن للمستخدم تعديل مستخدم آخر يمتلك دورًا أعلى منه أو نفس دوره
        // (هذه قاعدة عمل اختيارية لكنها جيدة للأمان)
        // سنتركها معلقة حاليًا لتبسيط الأمور، ولكن يمكن تفعيلها
        // if ($user->id !== $model->id && $model->hasRole('Admin') && !$user->hasRole('Super Admin')) {
        //     return false;
        // }

        return $user->can('user.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
  public function delete(User $user, User $model): bool
    {
        // القاعدة رقم 1: لا يمكن لأي شخص حذف مستخدم "Super Admin"
        if ($model->hasRole('Super Admin')) {
            return false;
        }
        // القاعدة رقم 2: التحقق من الصلاحية
        return $user->can('user.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // يمكننا ربطها بنفس صلاحية الحذف
        return $user->can('user.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // يمكننا ربطها بنفس صلاحية الحذف
        return $user->can('user.delete');
    }
}
