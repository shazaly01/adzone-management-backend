<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes - SPA Frontend Handler
|--------------------------------------------------------------------------
|
| هنا يتم إدارة مسارات الويب للنظام. هذا المسار مخصص لخدمة واجهة الـ Vue
| كمشروع مدمج (SPA). يقوم بالتقاط جميع الروابط وتوجيهها لملف الـ index.html
| المترجم، باستثناء المسارات التي تبدأ بـ /api لضمان عمل الـ Controllers بنجاح.
|
*/

// مسار المصيد العام (Catch-All Route) واشتراط استثناء الـ API والـ Health Check تلقائياً
Route::any('{any}', function () {
    $indexPath = public_path('index.html');

    // التحقق من وجود ملف بناء الواجهة لتجنب أخطاء السيرفر البيضاء
    if (File::exists($indexPath)) {
        return File::get($indexPath);
    }

    // استجابة نظيفة في حال عدم توفر ملفات الواجهة بعد في مجلد public
    return response()->json([
        'status' => false,
        'message' => 'Frontend build files (index.html) are missing in the public directory. Please run npm run build on your frontend project.',
    ], 404);
})->where('any', '^(?!api|up).*$');
