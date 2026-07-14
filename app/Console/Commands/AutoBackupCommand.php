<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OnlineBackupService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class AutoBackupCommand extends Command
{
    /**
     * الاسم البرمجي للأمر والمعاملات المتاحة
     *
     * @var string
     */
    protected $signature = 'panda:auto-backup {--force : تجاوز قفل منع تداخل وتكرار المهام}';

    /**
     * وصف الأمر البرمجي الذي يظهر في قائمة Artisan
     *
     * @var string
     */
    protected $description = 'إنشاء نسخة احتياطية تلقائية لقاعدة بيانات MySQL مع تدوير وحذف النسخ القديمة الزائدة عن 50 ملفاً';

    /**
     * تنفيذ الأمر البرمجي
     */
    public function handle(OnlineBackupService $backupService): int
    {
        $this->info('=== بدء عملية النسخ الاحتياطي التلقائي لمنظومة باندا الحسابية ===');

        $lockKey = 'panda_auto_backup_execution_lock';
        $isForce = $this->option('force');

        // التحقق من عدم وجود عملية نسخ أخرى نشطة حالياً لحماية السيرفر
        if (!$isForce && Cache::has($lockKey)) {
            $warningMessage = 'أمر النسخ الاحتياطي قيد التشغيل بالفعل في عملية أخرى، تم إلغاء المهمة الحالية لتجنب تداخل المهام وهبوط أداء السيرفر.';
            $this->warn($warningMessage);
            Log::warning($warningMessage);

            return Command::FAILURE;
        }

        // قفل المهمة برمجياً لمدة أقصاها ساعة (3600 ثانية) لمنع التداخل في حال تعليق العملية
        if (!$isForce) {
            Cache::put($lockKey, true, 3600);
        }

        try {
            // استدعاء الخدمة السحابية لإنشاء الباك اب وتفعيل تدوير الـ 50 ملفاً
            $result = $backupService->generateBackup();

            $successMessage = "تمت عملية النسخ الاحتياطي بنجاح تام. الملف: {$result['filename']} | الحجم: {$result['size']}";
            $this->info($successMessage);
            Log::info($successMessage);

            // تحرير القفل بعد النجاح الكامل
            Cache::forget($lockKey);

            return Command::SUCCESS;
        } catch (Exception $e) {
            $errorMessage = "حدث خطأ حرج أثناء عملية النسخ الاحتياطي التلقائي: " . $e->getMessage();
            $this->error($errorMessage);
            Log::error($errorMessage);

            // ضمان تحرير القفل حتى في حالة الفشل لإتاحة المحاولة القادمة
            Cache::forget($lockKey);

            return Command::FAILURE;
        }
    }
}
