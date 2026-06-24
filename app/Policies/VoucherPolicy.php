<?php

namespace App\Policies;

use App\Models\Voucher;
use App\Models\User;

class VoucherPolicy
{
    /**
     * تحديد من يستطيع استعراض قائمة السندات المالية (صرف وقبض)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('voucher.view');
    }

    /**
     * تحديد من يستطيع عرض تفاصيل سند مالي معين
     */
    public function view(User $user, Voucher $voucher): bool
    {
        return $user->hasPermissionTo('voucher.view');
    }

    /**
     * تحديد من يستطيع إنشاء سند مالي جديد (صرف أو قبض)
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('voucher.create');
    }

    /**
     * تحديد من يستطيع تعديل بيانات سند مالي قائم
     */
    public function update(User $user, Voucher $voucher): bool
    {
        return $user->hasPermissionTo('voucher.update');
    }

    /**
     * تحديد من يستطيع حذف السند مالياً وأرشفته (Soft Delete)
     */
    public function delete(User $user, Voucher $voucher): bool
    {
        return $user->hasPermissionTo('voucher.delete');
    }
}
