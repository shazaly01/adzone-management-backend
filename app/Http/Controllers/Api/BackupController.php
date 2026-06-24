<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * عرض قائمة النسخ الاحتياطية الموجودة.
     */
    public function index()
    {
        $backupName = config('backup.backup.name');

        // تعديل 1: استخدام disk('local') دائماً لتوحيد التعامل
        $disk = Storage::disk('local');

        if (!$disk->exists($backupName)) {
            // محاولة إنشاء المجلد إذا لم يكن موجوداً لتجنب الأخطاء
            $disk->makeDirectory($backupName);
            return response()->json(['data' => []]);
        }

        $files = $disk->files($backupName);
        $backups = [];

        foreach ($files as $file) {
            if (substr($file, -4) == '.zip') {
                $backups[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => $this->formatSize($disk->size($file)),
                    'date' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                ];
            }
        }

        $backups = array_reverse($backups);

        return response()->json(['data' => $backups]);
    }

    /**
     * إنشاء نسخة احتياطية جديدة.
     */
    public function store()
    {
        // تعديل 2 (هام جداً): زيادة وقت التنفيذ
        // عملية الباك بي تأخذ وقتاً (دقيقة أو أكثر) والريكوست العادي يموت بعد 30 ثانية
        // هذا السطر يمنع ظهور خطأ Timeout
        set_time_limit(0);
        ini_set('memory_limit', '-1'); // لضمان عدم توقف السكربت بسبب الذاكرة

        try {
            Artisan::call('backup:run');
            $output = Artisan::output();

            return response()->json([
                'message' => 'تم إنشاء النسخة الاحتياطية بنجاح.',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشل إنشاء النسخة الاحتياطية.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تنزيل ملف النسخة الاحتياطية.
     */
    public function download(Request $request)
    {
        // تعديل 3 (أمني): استخدام basename لمنع اختراق المسارات
        // هذا يمنع أي شخص من إرسال اسم ملف مثل "../../.env" لسرقة ملفات النظام
        $fileName = basename($request->query('file_name'));

        $backupName = config('backup.backup.name');
        $path = $backupName . '/' . $fileName;

        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'الملف غير موجود.'], 404);
        }

        return Storage::disk('local')->download($path);
    }

    /**
     * حذف نسخة احتياطية.
     */
    public function destroy(Request $request)
    {
        // نفس التعديل الأمني هنا أيضاً
        $fileName = basename($request->query('file_name'));

        $backupName = config('backup.backup.name');
        $path = $backupName . '/' . $fileName;

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
            return response()->json(['message' => 'تم حذف النسخة بنجاح.']);
        }

        return response()->json(['message' => 'الملف غير موجود.'], 404);
    }

    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
