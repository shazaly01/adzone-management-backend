<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\Api\EmployeeResource;
use App\Models\Account;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    /**
     * عرض قائمة الموظفين مع الفلترة والبحث والترقيم.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::query()->with('account');

        // الفلترة حسب البحث (الاسم، الهاتف، الرقم القومي، المسمى الوظيفي)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%")
                  ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        // الفلترة حسب الحالة (نشط / غير نشط)
        if ($request->has('is_active') && $request->input('is_active') !== null) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->input('per_page', 15);
        $employees = $query->latest('id')->paginate($perPage);

        return EmployeeResource::collection($employees);
    }

    /**
     * إنشاء موظف جديد وإنشاء حسابه المالي تلقائياً في شجرة الحسابات.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = DB::transaction(function () use ($request) {
            $data = $request->validated();

            // إنشاء حساب فرعي للموظف تحت حساب مستحقات رواتب الموظفين المالي (2104) إذا لم يُحدد حساب
            if (empty($data['account_id'])) {
                $parentAccount = Account::where('code', Account::CODE_EMPLOYEE_PAYROLL)->first();

                if ($parentAccount) {
                    $lastAccount = Account::where('parent_id', $parentAccount->id)->latest('id')->first();
                    $nextNumber = $lastAccount ? ((int) substr($lastAccount->code, -4)) + 1 : 1;
                    $accountCode = $parentAccount->code . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

                    $account = Account::create([
                        'parent_id'       => $parentAccount->id,
                        'code'            => $accountCode,
                        'name'            => 'حساب الموظف: ' . $data['name'],
                        'type'            => 'system',
                        'nature'          => 'credit',
                        'opening_balance' => 0.00,
                        'current_balance' => 0.00,
                    ]);

                    $data['account_id'] = $account->id;
                }
            }

            return Employee::create($data);
        });

        return (new EmployeeResource($employee->load('account')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * عرض تفاصيل موظف محدد.
     */
    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee->load('account'));
    }

    /**
     * تحديث بيانات الموظف وحسابه المرتبط.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        DB::transaction(function () use ($request, $employee) {
            $data = $request->validated();
            $employee->update($data);

            // تحديث اسم الحساب المالي المرتبط بالموظف في حال تغير الاسم
            if ($employee->account && isset($data['name'])) {
                $employee->account->update([
                    'name' => 'حساب الموظف: ' . $data['name'],
                ]);
            }
        });

        return new EmployeeResource($employee->load('account'));
    }

    /**
     * حذف موظف (Soft Delete).
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return response()->json([
            'message' => 'تم حذف الموظف بنجاح.',
        ]);
    }
}
