<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; // استدعاء المتحكم الأساسي من المجلد الأب
use App\Http\Requests\StorePendingInvoiceItemRequest;
use App\Http\Resources\PendingInvoiceItemResource;
use App\Models\PendingInvoiceItem;
use App\Services\VoiceParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PendingInvoiceItemController extends Controller
{
    /**
     * الخاصية الحاضنة لخدمة المعالجة الصوتية
     */
    protected VoiceParserService $voiceParserService;

    /**
     * حقن خدمة المعالجة الصوتية مباشرة في الباني لتبسيط الوصول
     */
    public function __construct(VoiceParserService $voiceParserService)
    {
        $this->voiceParserService = $voiceParserService;
    }

    /**
     * عرض كافة الأسطر الصوتية المؤقتة والمستبقاة الخاصة بالمستخدم لغرض الفحص والمعاينة
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        // جلب معرف المستخدم الحالي، مع وضع قيمة افتراضية للتجارب السريعة إن لم يكن الـ Auth مفعلاً بالكامل
        $userId = auth()->id() ?? 1;

        $pendingItems = PendingInvoiceItem::with('item')
            ->where('user_id', $userId)
            ->latest()
            ->get();

        return PendingInvoiceItemResource::collection($pendingItems);
    }

    /**
     * استقبال النص الصوتي أو اليدوي الخام، معالجته وتخزينه كـ سجل معلق للفحص المسبق
     *
     * @param StorePendingInvoiceItemRequest $request
     * @return JsonResponse
     */
    public function store(StorePendingInvoiceItemRequest $request): JsonResponse
    {
        // جلب معرف المستخدم الحالي، مع وضع قيمة افتراضية للتجارب السريعة إن لم يكن الـ Auth مفعلاً بالكامل
        $userId = auth()->id() ?? 1;

        // تمرير النص والعملية بالكامل إلى طبقة الخدمة المعزولة
        $pendingItem = $this->voiceParserService->parseAndStage(
            $request->validated()['raw_text'],
            $userId
        );

        // تحميل علاقة الصنف ذهنياً للتأكد من إرجاع بيانات المطابقة الفورية في الـ Resource
        $pendingItem->load('item');

        return (new PendingInvoiceItemResource($pendingItem))
            ->additional([
                'success' => true,
                'message' => 'تمت معالجة النص الصوتي بنجاح واستبقاؤه في جدول الفحص المؤقت.'
            ])
            ->response()
            ->setStatusCode(201);
    }
}
