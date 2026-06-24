<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    /**
     * تحدد ما إذا كان المستخدم يمكنه عرض قائمة الرسائل (Index)
     */
    public function viewAny(User $user): bool
    {
        return $user->can('message.view');
    }

    /**
     * تحدد ما إذا كان المستخدم يمكنه عرض تفاصيل رسالة معينة (Show)
     */
    public function view(User $user, Message $message): bool
    {
        return $user->can('message.view');
    }

    /**
     * تحدد ما إذا كان المستخدم يمكنه إنشاء (إرسال) رسائل جديدة
     */
    public function create(User $user): bool
    {
        return $user->can('message.create');
    }

    /**
     * تحدد ما إذا كان المستخدم يمكنه حذف سجل رسالة
     * (عادة نتركها للمدراء فقط)
     */
    public function delete(User $user, Message $message): bool
    {
        return $user->can('message.delete');
    }

    // ملاحظة: لم نضع دالة update لأن الرسائل النصية بمجرد إرسالها
    // لا تُعدل منطقياً، بل تُحفظ كأرشيف فقط.
}
