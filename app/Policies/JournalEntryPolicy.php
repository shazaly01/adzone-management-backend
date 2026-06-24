<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('journal_entry.view');
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('journal_entry.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('journal_entry.create');
    }

    /**
     * تحديث القيود والسندات المالية
     */
    public function update(User $user, JournalEntry $journalEntry): bool
    {
        // محاسبياً وسيادياً: يمكنك هنا قفل التعديل إذا مر على السند فترة زمنية معينة
        // أو إذا تم إقفال السنة المالية، ولكن هنا نعتمد على صلاحية النظام الأساسية
        return $user->hasPermissionTo('journal_entry.update');
    }

    /**
     * حذف القيود والسندات المالية
     */
    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        // في الأنظمة المالية الحساسة، يفضل جداً إلغاء السند بقيد عكسي بدلاً من الحذف الناعم
        // ولكن طالما متاح، نحصر الصلاحية فقط لمن يملك امتياز الحذف المالي
        return $user->hasPermissionTo('journal_entry.delete');
    }

    public function restore(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermissionTo('journal_entry.delete');
    }

    public function forceDelete(User $user, JournalEntry $journalEntry): bool
    {
        return false; // ممنوع الحذف النهائي والقطعي من الداتابيز للحسابات المالية
    }
}
