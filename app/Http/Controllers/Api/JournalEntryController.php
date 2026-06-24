<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntry\StoreJournalEntryRequest;
use App\Http\Requests\JournalEntry\UpdateJournalEntryRequest;
use App\Http\Resources\Api\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Exception;

class JournalEntryController extends Controller
{
    protected JournalEntryService $journalService;

    public function __construct(JournalEntryService $journalService)
    {
        $this->journalService = $journalService;

        // تطبيق حماية الصلاحيات المعتمدة على Spatie عبر الـ Policy تلقائياً
        $this->authorizeResource(JournalEntry::class, 'journal_entry');
    }

    /**
     * عرض قائمة القيود والسندات المالية مع التحميل المسبق الشامل للحسابات المساعدة
     */
    public function index(): JsonResponse
    {
        // تفعيل الـ Eager Loading لعلاقة الـ subLedger لمنع استعلامات N+1 المتكررة وحماية الـ RAM
        $entries = JournalEntry::with(['lines.account', 'lines.subLedger', 'user'])->latest()->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => JournalEntryResource::collection($entries)->response()->getData(true)
        ]);
    }

    /**
     * إنشاء قيد أو سند مالي جديد وتمريره للخدمة لتحديث الأرصدة
     */
    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        try {
            // تفويض العملية بالكامل لطبقة المعاملات المحمية في الـ Service
            $journalEntry = $this->journalService->createEntry($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل القيد المالي وتحديث أرصدة الحسابات بنجاح.',
                'data'    => new JournalEntryResource($journalEntry->load(['lines.account', 'lines.subLedger']))
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية تسجيل القيد المالي: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل قيد أو سند مالي محدد
     */
    public function show(JournalEntry $journalEntry): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new JournalEntryResource($journalEntry->load(['lines.account', 'lines.subLedger', 'user']))
        ]);
    }

    /**
     * تعديل القيد المالي
     */
    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): JsonResponse
    {
        try {
            // استدعاء الخدمة المحدثة لتطبيق استراتيجية مسح الأثر والتصعيد الذكي للحسابات العكسية
            $updatedEntry = $this->journalService->updateEntry($journalEntry, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم تعديل القيد المالي وإعادة احتساب الأرصدة بنجاح.',
                'data'    => new JournalEntryResource($updatedEntry->load(['lines.account', 'lines.subLedger']))
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * حذف قيد مالي وعكس أثره تماماً
     */
    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        try {
            $this->journalService->deleteEntry($journalEntry);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف القيد المالي وعكس أثره من الحسابات بنجاح.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية حذف القيد: ' . $e->getMessage()
            ], 500);
        }
    }
}
