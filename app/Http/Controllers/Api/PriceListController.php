<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PriceListResource;
use App\Models\PriceList;
use Illuminate\Http\JsonResponse;

class PriceListController extends Controller
{
    /**
     * جلب قائمة فئات الأسعار المعتمدة لتغذية الـ Store والمصفوفة في الفرونت إند
     */
    public function index(): JsonResponse
    {
        // جلب الفئات الافتراضية المرتبة تصاعدياً (الحذف الناعم يتم معالجته تلقائياً عبر الموديل)
        $priceLists = PriceList::orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => PriceListResource::collection($priceLists)
        ]);
    }
}
