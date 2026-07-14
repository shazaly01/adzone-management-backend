<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnlineBackupService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Exception;

class BackupController extends Controller
{
    protected OnlineBackupService $backupService;

    /**
     * حقن خدمة النسخ الاحتياطي في الـ Controller
     */
    public function __construct(OnlineBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * عرض قائمة النسخ الاحتياطية المتوفرة أونلاين على السيرفر
     */
    public function index(): JsonResponse
    {
        try {
            $backups = $this->backupService->getBackupsList();

            return response()->json([
                'success' => true,
                'data' => $backups
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر جلب قائمة النسخ الاحتياطية.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إنشاء نسخة احتياطية جديدة لقاعدة البيانات
     */
    public function store(): JsonResponse
    {
        // زيادة حد وقت التنفيذ والذاكرة لتفادي انهيار الريكوست أثناء الضغط
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            $backupInfo = $this->backupService->generateBackup();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء النسخة الاحتياطية أونلاين بنجاح تام وتم تدوير النسخ الزائدة.',
                'data' => $backupInfo
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء النسخة الاحتياطية أونلاين.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * طلب توليد رابط تحميل آمن وموقع زمنياً لسحب النسخة الاحتياطية خارجياً لجهاز العميل
     */
    public function getDownloadUrl(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string'
        ]);

        $fileName = basename($request->input('file_name'));

        try {
            $downloadUrl = $this->backupService->generateDownloadUrl($fileName);

            return response()->json([
                'success' => true,
                'download_url' => $downloadUrl
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر توليد رابط التحميل الآمن.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تنفيذ التحميل الفعلي للملف الموقّع برمجياً (يتم استدعاؤه بواسطة رابط الـ Signed URL)
     */
    public function download(Request $request): BinaryFileResponse|JsonResponse
    {
        // 1. التحقق الحاسم من سلامة وصلاحية التوقيع الرقمي للرابط المولد
        if (!$request->hasValidSignature()) {
            return response()->json([
                'success' => false,
                'message' => 'رابط التحميل غير صالح أو منتهي الصلاحية الأمنية (صلاحية الرابط دقيقتين فقط).'
            ], 403);
        }

        $fileName = basename($request->query('file_name'));
        $filePath = storage_path('app' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'online' . DIRECTORY_SEPARATOR . $fileName);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'الملف المطلوب غير موجود على السيرفر.'
            ], 404);
        }

        return response()->download($filePath);
    }

    /**
     * استعادة قاعدة البيانات من ملف نسخة احتياطية (.sql.gz) مع التراجع الآمن في حال الكوارث
     */
    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string'
        ]);

        // زيادة حدود التنفيذ لضمان عدم انقطاع الاستعادة في منتصف العملية
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $fileName = basename($request->input('file_name'));

        try {
            $result = $this->backupService->restoreBackup($fileName);

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية استعادة قاعدة البيانات.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف نسخة احتياطية بشكل نهائي من السيرفر
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string'
        ]);

        $fileName = basename($request->input('file_name'));

        try {
            $deleted = $this->backupService->deleteBackup($fileName);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف ملف النسخة الاحتياطية بنجاح تام من السيرفر.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'الملف المطلوب غير موجود أو تعذر حذفه.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء محاولة حذف الملف.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
