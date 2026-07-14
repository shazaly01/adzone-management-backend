<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Task Scheduling
|--------------------------------------------------------------------------
|
| هذا الملف هو المسؤول في لارافيل 11 عن تسجيل الأوامر التفاعلية القائمة على
| الـ Closures وجدولة كافة المهام التلقائية التي تعمل في خلفية النظام.
|
*/

// أمر الإلهام الافتراضي (يمكنك إبقاؤه لتجربة تشغيل الأوامر يدوياً)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// جدولة أمر النسخ الاحتياطي التلقائي لمنظومة باندا ليعمل بدقة كل 5 ساعات
Schedule::command('panda:auto-backup')
    ->cron('0 */5 * * *')               // تشغيل تلقائي منتظم كل 5 ساعات
    ->withoutOverlapping(60)           // منع تداخل المهمة إذا استغرقت الدورة السابقة وقتاً أطول (قفل لمدة ساعة)
    ->runInBackground()                // تشغيل في عملية خلفية معزولة لعدم تعطيل مهام المجدول الأخرى
    ->onOneServer();                   // حماية إضافية: التشغيل على سيرفر واحد فقط في حال التوسع مستقبلاً لعدة سيرفرات (Cluster)
