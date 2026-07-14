<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnlineBackupService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    /**
     * خدمة النسخ الاحتياطي السحابي لإدارة العمليات المعقدة
     *
     * @var OnlineBackupService
     */
    protected OnlineBackupService $backupService;

    /**
     * حقن الخدمة داخل وحدة التحكم
     *
     * @param OnlineBackupService $backupService
     */
    public function __construct(OnlineBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * جلب قائمة كافة ملفات النسخ الاحتياطي المتوفرة على السيرفر
     * مسار الوصول: GET /api/backups
     * الصلاحية المطلوبة: backup.view
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $backups = $this->backupService->getBackupsList();

            return response()->json([
                'success' => true,
                'data' => $backups
            ]);
        } catch (\Exception $e) {
            Log::error('فشل جلب قائمة النسخ الاحتياطية: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ فني أثناء جلب قائمة النسخ الاحتياطية من السيرفر.'
            ], 500);
        }
    }

    /**
     * إنشاء نسخة احتياطية جديدة لقاعدة البيانات يدوياً
     * مسار الوصول: POST /api/backups
     * الصلاحية المطلوبة: backup.create
     *
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        try {
            // استدعاء الدالة الصحيحة المتوافقة مع السيرفس الخاص بك
            $backup = $this->backupService->generateBackup();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء النسخة الاحتياطية بنجاح وتدوير الملفات القديمة تلقائياً.',
                'data' => $backup
            ]);
        } catch (\Exception $e) {
            Log::error('فشل إنشاء نسخة احتياطية يدوية: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'تعذر إتمام عملية النسخ الاحتياطي: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحميل ملف النسخة الاحتياطية مباشرة وبأمان
     * مسار الوصول: GET /api/backups/download
     * الصلاحية المطلوبة: backup.download
     *
     * @param Request $request
     * @return BinaryFileResponse|JsonResponse
     */
    public function download(Request $request): BinaryFileResponse|JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string'
        ]);

        // حماية حاسمة ضد تنقل المسارات وثغرة (Path Traversal)
        $fileName = basename($request->input('file_name'));
        $filePath = storage_path('app' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'online' . DIRECTORY_SEPARATOR . $fileName);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'الملف المطلوب غير موجود على السيرفر، قد يكون تم حذفه تلقائياً أثناء التدوير.'
            ], 404);
        }

        return response()->download($filePath);
    }

    /**
     * استعادة قاعدة البيانات من ملف نسخة احتياطية محدد مع حماية الطوارئ
     * مسار الوصول: POST /api/backups/restore
     * الصلاحية المطلوبة: backup.restore
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string'
        ]);

        try {
            $fileName = basename($request->input('file_name'));

            // تشغيل محرك الاستعادة والتراجع التلقائي في حال الفشل
            $result = $this->backupService->restoreBackup($fileName);

            return response()->json([
                'success' => true,
                'message' => 'تمت استعادة قاعدة البيانات وتدقيق القيود وإعادة بناء الجداول بنجاح تام.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('فشلت عملية استعادة قاعدة البيانات: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'تعذرت الاستعادة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف ملف نسخة احتياطية نهائياً من السيرفر
     * مسار الوصول: DELETE /api/backups
     * الصلاحية المطلوبة: backup.delete
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string'
        ]);

        try {
            $fileName = basename($request->input('file_name'));

            $this->backupService->deleteBackup($fileName);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف وتدمير ملف النسخة الاحتياطية من السيرفر بنجاح.'
            ]);
        } catch (\Exception $e) {
            Log::error('فشل حذف ملف النسخة الاحتياطية: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء محاولة حذف الملف من السيرفر: ' . $e->getMessage()
            ], 500);
        }
    }
}
