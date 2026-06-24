<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Beneficiary;
use App\Models\Area;
use App\Services\SmsService;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\Api\MessageResource;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
        // ربط الـ Policy
        $this->authorizeResource(Message::class, 'message');
    }

    /**
     * عرض سجل الرسائل
     */
    public function index(): JsonResponse
    {
        $messages = Message::with(['beneficiary', 'area', 'sender'])
                           ->latest()
                           ->paginate(15);

        return response()->json([
            'status' => true,
            'data'   => MessageResource::collection($messages),
            'meta'   => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
            ]
        ]);
    }

    // /**
    //  * إرسال رسالة (فردية أو لمنطقة)
    //  */
    // public function store(StoreMessageRequest $request): JsonResponse
    // {
    //     $content = $request->content;
    //     $senderId = auth()->id();
    //     $count = 0;

    //     if ($request->type === 'individual') {
    //         $beneficiary = Beneficiary::findOrFail($request->beneficiary_id);
    //         $this->smsService->sendIndividual($beneficiary, $content, $senderId);
    //         $count = 1;
    //     } else {
    //         $area = Area::findOrFail($request->area_id);
    //         $count = $this->smsService->sendToArea($area, $content, $senderId);
    //     }

    //     return response()->json([
    //         'status'  => true,
    //         'message' => "تم جدولة إرسال {$count} رسالة بنجاح.",
    //     ], 201);
    // }

    /**
     * حذف سجل رسالة
     */
    public function destroy(Message $message): JsonResponse
    {
        $message->delete();
        return response()->json([
            'status'  => true,
            'message' => 'تم حذف سجل الرسالة بنجاح.'
        ]);
    }
}
