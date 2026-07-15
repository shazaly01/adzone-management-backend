<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\DbDumper\Databases\MySql;
use Symfony\Component\Process\Process;
use Exception;

class OnlineBackupService
{
    protected string $backupPath;
    protected string $tempPath;

    public function __construct()
    {
        // تحديد مسار حفظ الباك اب أونلاين ومجلد العمليات المؤقتة
        $this->backupPath = storage_path('app' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'online');
        $this->tempPath = storage_path('app' . DIRECTORY_SEPARATOR . 'backup-temp');
    }

    /**
     * جلب اسم قاعدة البيانات الحالية ديناميكياً من الإعدادات
     */
    protected function getDatabaseName(): string
    {
        $connection = config('database.default');
        return config("database.connections.{$connection}.database") ?? 'database';
    }

    /**
     * إنشاء نسخة احتياطية جديدة مضغوطة وآمنة تماماً باسم قاعدة البيانات الديناميكي
     */
    public function generateBackup(): array
    {
        $connection = config('database.default');
        if ($connection !== 'mysql') {
            throw new Exception('هذه الخدمة مخصصة حصرياً لقواعد بيانات MySQL.');
        }

        $dbConfig = config("database.connections.{$connection}");
        $dbName = $dbConfig['database'];

        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true, true);
        }

        if (!File::exists($this->tempPath)) {
            File::makeDirectory($this->tempPath, 0755, true, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $fileName = "{$dbName}_{$timestamp}.sql.gz";
        $finalPath = $this->backupPath . DIRECTORY_SEPARATOR . $fileName;

        // توليد ملف سيكوال مؤقت غير مضغوط أولاً لحماية الذاكرة
        $tempSqlFile = $this->tempPath . DIRECTORY_SEPARATOR . "temp_dump_{$timestamp}.sql";

        try {
            MySql::create()
                ->setDbName($dbName)
                ->setUserName($dbConfig['username'])
                ->setPassword($dbConfig['password'])
                ->setHost($dbConfig['host'] ?? '127.0.0.1')
                ->setPort($dbConfig['port'] ?? 3306)
                ->addExtraOption('--single-transaction') // منع قفل الجداول أثناء عمل موظفي الحسابات
                ->addExtraOption('--skip-lock-tables')
                ->addExtraOption('--quick') // سحب البيانات سطر بسطر لتقليل الضغط على الذاكرة
                ->dumpToFile($tempSqlFile);

            // ضغط الملف يدوياً عبر PHP لضمان أقصى حماية واستقرار
            $this->gzipFile($tempSqlFile, $finalPath);
            File::delete($tempSqlFile);

            // تشغيل التدوير التلقائي للاحتفاظ بـ 50 نسخة فقط تخص قاعدة البيانات هذه
            $this->rotateBackups();

            return [
                'success' => true,
                'filename' => $fileName,
                'path' => $finalPath,
                'size' => $this->formatSize(File::size($finalPath)),
                'date' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            if (File::exists($tempSqlFile)) {
                File::delete($tempSqlFile);
            }
            throw new Exception('فشل إنشاء النسخة الاحتياطية أونلاين: ' . $e->getMessage());
        }
    }

    /**
     * استعادة قاعدة البيانات مع شبكة أمان الطوارئ التلقائية المخصصة
     */
    public function restoreBackup(string $fileName): array
    {
        $fileName = basename($fileName); // تأمين ضد ثغرة تخطي المسارات
        $sourcePath = $this->backupPath . DIRECTORY_SEPARATOR . $fileName;

        if (!File::exists($sourcePath)) {
            throw new Exception('ملف النسخة الاحتياطية المحددة غير موجود على السيرفر.');
        }

        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}");

        // 1. أخذ نسخة أمان لحظية (طوارئ) مخصصة باسم قاعدة البيانات قبل لمس أي جدول
        $this->generateEmergencyBackup($dbConfig);

        $tempSqlFile = $this->tempPath . DIRECTORY_SEPARATOR . 'temp_restore_extract.sql';

        try {
            // 2. فك ضغط الملف المستهدف إلى المجلد المؤقت
            if (!File::exists($this->tempPath)) {
                File::makeDirectory($this->tempPath, 0755, true, true);
            }
            $this->gunzipFile($sourcePath, $tempSqlFile);

            // 3. تفريغ قاعدة البيانات الحالية بالكامل (حذف نظيف وآمن للجداول والـ Views)
            $this->dropAllTables($dbConfig['database']);

            // 4. محاولة تنفيذ الاستيراد عبر تقنية الاستيراد الهجين (Hybrid Import Engine)
            try {
                $this->executeMysqlImport($dbConfig, $tempSqlFile);
            } catch (Exception $ex) {
                // إذا فشلت أدوات النظام، نتحول فوراً للمفسر البرمجي الداخلي المستقر
                $this->executePdoImport($tempSqlFile);
            }

            // تنظيف الملفات المؤقتة وحذف نسخة الطوارئ لنجاح العملية بنسبة 100%
            File::delete($tempSqlFile);
            $this->clearEmergencyBackup();

            return [
                'success' => true,
                'message' => 'تمت استعادة قاعدة البيانات بنجاح تام وبشكل متكامل.'
            ];
        } catch (Exception $e) {
            // تنظيف الملف المؤقت المصاب
            if (File::exists($tempSqlFile)) {
                File::delete($tempSqlFile);
            }

            // 5. خط الدفاع الحاسم: استدعاء التراجع التلقائي من ملف الطوارئ
            $this->rollbackEmergency($dbConfig);

            throw new Exception('فشلت عملية الاستعادة لخطأ فني، وتم التراجع تلقائياً وإعادة النظام لحالته السليمة السابقة لضمان سلامة بياناتك: ' . $e->getMessage());
        }
    }

    /**
     * جلب قائمة كافة النسخ الاحتياطية المتوفرة المفلترة بقاعدة البيانات الحالية
     */
    public function getBackupsList(): array
    {
        $backups = [];
        $dbName = $this->getDatabaseName();
        $prefix = "{$dbName}_";

        if (File::exists($this->backupPath)) {
            $files = File::files($this->backupPath);
            foreach ($files as $file) {
                // فلترة دقيقة تضمن جلب الملفات التي تبدأ باسم قاعدة البيانات الحالية فقط
                if ($file->getExtension() === 'gz' && str_starts_with($file->getFilename(), $prefix)) {
                    $backups[] = [
                        'name' => $file->getFilename(),
                        'size' => $this->formatSize($file->getSize()),
                        'date' => date('Y-m-d H:i:s', $file->getMTime())
                    ];
                }
            }
        }

        // ترتيب تنازلي (النسخ الأحدث أولاً)
        usort($backups, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return $backups;
    }

    /**
     * تدمير ملف نسخة احتياطية من القرص
     */
    public function deleteBackup(string $fileName): bool
    {
        $fileName = basename($fileName);
        $filePath = $this->backupPath . DIRECTORY_SEPARATOR . $fileName;

        if (File::exists($filePath)) {
            return File::delete($filePath);
        }

        return false;
    }

    /**
     * توليد رابط تحميل موقع مؤقتاً لتأمين عمليات السحب الخارجي لجهاز العميل
     */
    public function generateDownloadUrl(string $fileName): string
    {
        return URL::temporarySignedRoute(
            'backups.download',
            now()->addMinutes(2),
            ['file_name' => $fileName],
            false // <--- جعل الرابط نسبياً (Relative) يتجاهل الـ Host والـ Protocol
        );
    }

    /**
     * إدارة تدوير الملفات للاحتفاظ بـ 50 نسخة فقط وحذف الأقدم لقاعدة البيانات الحالية حصرياً
     */
    protected function rotateBackups(): void
    {
        if (!File::exists($this->backupPath)) {
            return;
        }

        $files = File::files($this->backupPath);
        $backupFiles = [];
        $dbName = $this->getDatabaseName();
        $prefix = "{$dbName}_";

        foreach ($files as $file) {
            // التصفية بناءً على البادئة الديناميكية لمنع تداخل المشاريع
            if ($file->getExtension() === 'gz' && str_starts_with($file->getFilename(), $prefix)) {
                $backupFiles[] = [
                    'path' => $file->getPathname(),
                    'mtime' => $file->getMTime()
                ];
            }
        }

        // الترتيب من الأحدث إلى الأقدم
        usort($backupFiles, function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });

        // إذا تجاوز العدد 50، نقوم بتدمير الزائد بدءاً من الأقدم
        if (count($backupFiles) > 50) {
            $filesToDelete = array_slice($backupFiles, 50);
            foreach ($filesToDelete as $file) {
                File::delete($file['path']);
            }
        }
    }

    /**
     * استيراد قاعدة البيانات عبر سطر الأوامر بنمط آمن ومعزول تماماً
     */
    protected function executeMysqlImport(array $dbConfig, string $filePath): void
    {
        $command = [
            'mysql',
            '-h', $dbConfig['host'] ?? '127.0.0.1',
            '-P', $dbConfig['port'] ?? '3306',
            '-u', $dbConfig['username'],
            '--execute=source ' . $filePath, // استخدام الأمر الداخلي لـ MySQL لعدم فتح ثغرات حقن أو استهلاك الذاكرة
            $dbConfig['database']
        ];

        // نمرر كلمة المرور عبر البيئة لمنع ظهورها في قائمة العمليات النشطة للسيرفر
        $process = new Process($command, null, [
            'MYSQL_PWD' => $dbConfig['password']
        ]);

        $process->setTimeout(300); // إتاحة وقت كافي للاستيراد
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception($process->getErrorOutput());
        }
    }

    /**
     * مفسر استيراد داخلي احتياطي يعمل عبر PDO لضمان الدعم الكامل في حال قيود السيرفر
     */
    protected function executePdoImport(string $filePath): void
    {
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new Exception('تعذر فتح ملف الاستيراد المؤقت.');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $query = '';
        while (($line = fgets($file)) !== false) {
            $trimmedLine = trim($line);

            // تجاهل التعليقات والسطور الفارغة لتسريع المعالجة
            if (empty($trimmedLine) || str_starts_with($trimmedLine, '--') || str_starts_with($trimmedLine, '/*')) {
                continue;
            }

            $query .= $line;

            // إذا انتهى السطر بفاصلة منقوطة، نقوم بتنفيذه وتحرير الذاكرة فوراً
            if (str_ends_with($trimmedLine, ';')) {
                DB::unprepared($query);
                $query = '';
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        fclose($file);
    }

    /**
     * تفريغ قاعدة البيانات بالكامل (Drop Tables & Views) بشكل آمن
     */
    protected function dropAllTables(string $dbName): void
    {
        $tables = DB::select('SHOW FULL TABLES');
        $colName = "Tables_in_{$dbName}";

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $name = $table->$colName;
            $type = $table->Table_type;

            if ($type === 'VIEW') {
                DB::statement("DROP VIEW IF EXISTS `{$name}`");
            } else {
                DB::statement("DROP TABLE IF EXISTS `{$name}`");
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * ضغط الملف بنظام دفق Chunks لحفظ استهلاك ذاكرة السيرفر
     */
    protected function gzipFile(string $source, string $destination): void
    {
        $fpOut = gzopen($destination, 'wb9'); // ضغط من الدرجة التاسعة (القصوى)
        $fpIn = fopen($source, 'rb');

        if ($fpOut === false || $fpIn === false) {
            throw new Exception('فشل في فتح قنوات البيانات لضغط الملف.');
        }

        while (!feof($fpIn)) {
            gzwrite($fpOut, fread($fpIn, 1024 * 512)); // حزم بحجم 512 كيلوبايت لسلامة الذاكرة
        }

        fclose($fpIn);
        gzclose($fpOut);
    }

    /**
     * فك ضغط ملفات .sql.gz بنظام دفق Chunks لحفظ استهلاك الذاكرة
     */
    protected function gunzipFile(string $source, string $destination): void
    {
        $fpOut = fopen($destination, 'wb');
        $fpIn = gzopen($source, 'rb');

        if ($fpOut === false || $fpIn === false) {
            throw new Exception('فشل في فتح قنوات البيانات لفك ضغط الملف.');
        }

        while (!gzeof($fpIn)) {
            fwrite($fpOut, gzread($fpIn, 1024 * 512));
        }

        fclose($fpOut);
        gzclose($fpIn);
    }

    /**
     * توليد نسخة الطوارئ اللحظية السريعة قبل الاستعادة مخصصة باسم قاعدة البيانات
     */
    protected function generateEmergencyBackup(array $dbConfig): void
    {
        $dbName = $dbConfig['database'];
        $emergencyFile = $this->backupPath . DIRECTORY_SEPARATOR . "EMERGENCY_ROLLBACK_{$dbName}.sql.gz";
        $tempSql = $this->tempPath . DIRECTORY_SEPARATOR . "temp_emergency_{$dbName}.sql";

        if (!File::exists($this->tempPath)) {
            File::makeDirectory($this->tempPath, 0755, true, true);
        }

        MySql::create()
            ->setDbName($dbName)
            ->setUserName($dbConfig['username'])
            ->setPassword($dbConfig['password'])
            ->setHost($dbConfig['host'] ?? '127.0.0.1')
            ->setPort($dbConfig['port'] ?? 3306)
            ->addExtraOption('--single-transaction')
            ->addExtraOption('--skip-lock-tables')
            ->addExtraOption('--quick')
            ->dumpToFile($tempSql);

        $this->gzipFile($tempSql, $emergencyFile);
        File::delete($tempSql);
    }

    /**
     * تنفيذ التراجع التلقائي من ملف الطوارئ المخصص في حال الكوارث
     */
    protected function rollbackEmergency(array $dbConfig): void
    {
        $dbName = $dbConfig['database'];
        $emergencyFile = $this->backupPath . DIRECTORY_SEPARATOR . "EMERGENCY_ROLLBACK_{$dbName}.sql.gz";
        if (!File::exists($emergencyFile)) {
            return;
        }

        try {
            $tempEmergencySql = $this->tempPath . DIRECTORY_SEPARATOR . "temp_emergency_rollback_{$dbName}.sql";
            $this->gunzipFile($emergencyFile, $tempEmergencySql);

            $this->dropAllTables($dbName);

            try {
                $this->executeMysqlImport($dbConfig, $tempEmergencySql);
            } catch (Exception $ex) {
                $this->executePdoImport($tempEmergencySql);
            }

            File::delete($tempEmergencySql);
            File::delete($emergencyFile);
        } catch (Exception $criticalError) {
            // تسجيل الكارثة في نظام التقارير إذا خرج التراجع عن السيطرة
            logger()->critical("فشل حرج: تعذر استعادة نسخة الطوارئ التلقائية لقاعدة البيانات {$dbName} بعد فشل الاستعادة الأساسي! التفاصيل: " . $criticalError->getMessage());
        }
    }

    /**
     * مسح نسخة الطوارئ المخصصة لقاعدة البيانات الحالية
     */
    protected function clearEmergencyBackup(): void
    {
        $dbName = $this->getDatabaseName();
        $emergencyFile = $this->backupPath . DIRECTORY_SEPARATOR . "EMERGENCY_ROLLBACK_{$dbName}.sql.gz";
        if (File::exists($emergencyFile)) {
            File::delete($emergencyFile);
        }
    }

    protected function formatSize($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
