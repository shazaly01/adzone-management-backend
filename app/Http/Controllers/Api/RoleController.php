<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\Api\PermissionResource;
use App\Http\Resources\Api\RoleResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        // استخدام authorizeResource لتطبيق الـ Policy تلقائيًا
        // نحتاج إلى تحديد 'parameter' لأن Laravel تتوقع 'role' ونحن نستخدم 'role'
        $this->authorizeResource(Role::class, 'role');
    }

    public function index()
    {
        $roles = Role::with('permissions')->latest()->paginate(15);
        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $validated['name'], 'guard_name' => 'api']);
            $role->syncPermissions($validated['permissions']);
            DB::commit();

            return new RoleResource($role->load('permissions'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create role.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Role $role)
    {
        return new RoleResource($role->load('permissions'));
    }

 public function update(UpdateRoleRequest $request, Role $role)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            // 1. تحديث اسم الدور دائماً بناءً على البيانات المفحوصة
            $role->update(['name' => $validated['name']]);

            // 2. التحقق الصارم: يتم تحديث الصلاحيات فقط إذا كانت متواجدة في الطلب المرسل
            // هذا يمنع الخطأ ويمنع مسح الصلاحيات القديمة عند تعديل الاسم بمفرده
            if (array_key_exists('permissions', $validated)) {
                $role->syncPermissions($validated['permissions'] ?? []);
            }

            DB::commit();

            return new RoleResource($role->load('permissions'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update role.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Role $role)
    {
        // 1. استدعاء الـ Policy للتحقق من الصلاحية
        $this->authorize('delete', $role);

        // --- بداية التعديل: إعادة قاعدة العمل ---
        // 2. التحقق من قاعدة العمل (يتم تنفيذها دائمًا بعد التحقق من الصلاحية)
        if (in_array($role->name, ['Super Admin', 'Admin', 'User'])) {
            // استخدام abort helper function هو الأنسب هنا
            abort(Response::HTTP_FORBIDDEN, 'Cannot delete default roles.');
        }
        // --- نهاية التعديل ---

        // 3. تنفيذ الحذف إذا تم تجاوز كل عمليات التحقق
        $role->delete();

        return response()->noContent();
    }
    /**
     * Get all available permissions.
     * هذه الدالة لا يتم حمايتها بـ authorizeResource، لذا نضيف الصلاحية يدويًا
     */
   public function getAllPermissions()
    {
        $this->authorize('viewAny', Role::class);

        // 1. قاموس الترجمة
       $groupTranslations = [
            'dashboard'        => 'لوحة التحكم',
            'user'             => 'المستخدمون',
            'role'             => 'الأدوار الصلاحية',
            'company'          => 'الشركات',
            'setting'          => 'الإعدادات العامة',
            'backup'           => 'النسخ الاحتياطي',
            'category'         => 'مجموعات الأصناف',
            'unit'             => 'وحدات القياس',
            'store'            => 'المخازن والمستودعات',
            'item'             => 'دليل الأصناف والخدمات',
            'customer'         => 'إدارة العملاء',
            'supplier'         => 'إدارة الموردين',
            'account'          => 'شجرة الحسابات',
            'treasury'         => 'الخزائن المالية',
            'bank'             => 'الحسابات البنكية',
            'expense'          => 'بنود المصروفات',
            'purchase'         => 'فواتير المشتريات',
            'sale'             => 'فواتير المبيعات',
            'voucher'          => 'سندات القبض والصرف',
            'stock_adjustment' => 'التسويات الجردية',
            'opening_stock'    => 'بضاعة أول المدة',
            'journal_entry'    => 'القيود اليومية المركبة',
            'report'           => 'قسم التقارير الموحد',
        ];

        $actionTranslations = [
            'view'               => 'عرض',
            'create'             => 'إنشاء',
            'update'             => 'تعديل',
            'delete'             => 'حذف',
            'swap_raw_materials' => 'ملاءمة خامات الورشة', // [حقن برمجى حاسم]: لتمرير صلاحية الفني للواجهة
        ];

        // 2. جلب كل الصلاحيات وتجميعها حسب المجموعة
        $permissions = Permission::where('guard_name', 'api')->get()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        // 3. بناء هيكل المجموعات (groups)
        $structuredGroups = [];
        foreach ($permissions as $groupKey => $permissionGroup) {
            $groupPermissions = [];
            foreach ($permissionGroup as $p) {
                $actionKey = explode('.', $p->name)[1];
                // تأكد من أن الإجراء موجود في قاموس الترجمة قبل إضافته
                if (array_key_exists($actionKey, $actionTranslations)) {
                    $groupPermissions[] = [
                        'id' => $p->id,
                        'action' => $actionKey,
                    ];
                }
            }

            // أضف المجموعة فقط إذا كانت تحتوي على صلاحيات
            if (!empty($groupPermissions)) {
                $structuredGroups[] = [
                    'key' => $groupKey,
                    'display_name' => $groupTranslations[$groupKey] ?? $groupKey,
                    'permissions' => $groupPermissions,
                ];
            }
        }

        // 4. بناء هيكل الإجراءات (actions)
        $allActions = collect($actionTranslations)->map(function ($displayName, $key) {
            return ['key' => $key, 'display_name' => $displayName];
        })->values();

        // 5. إرجاع الاستجابة النهائية
        return response()->json([
            'groups' => $structuredGroups,
            'actions' => $allActions,
        ]);
    }
}
