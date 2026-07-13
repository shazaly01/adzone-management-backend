<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Item;
use App\Models\Sale;

class UpdateSaleRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لتحديث المستند بناءً على السياسات الأمنية للـ API
     */
    public function authorize(): bool
    {
        $sale = $this->route('sale');
        return $this->user()->can('update', $sale);
    }

    /**
     * قواعد التحقق الصارمة لتحديث رأس وسطور المبيعات
     */
    public function rules(): array
    {
        return [
            // --- قواعد رأس الفاتورة ---
            'invoice_type'    => ['required', Rule::in(['sale', 'return'])],
            'parent_id'       => [
                'nullable',
                Rule::requiredIf($this->invoice_type === 'return'),
                'exists:sales,id'
            ],
            'store_id'        => ['required', 'exists:stores,id'],

            // حقن واشتراط الخزنة ماليّاً فقط في حال كان الدفع نقداً
            'treasury_id'     => [
                Rule::requiredIf($this->payment_type === 'cash'),
                'nullable',
                'exists:treasuries,id'
            ],

            // حقن واشتراط الحساب البنكي ماليّاً فقط في حال كان الدفع شبكة
            'bank_id'         => [
                Rule::requiredIf($this->payment_type === 'card'),
                'nullable',
                'exists:banks,id'
            ],

            'customer_id'     => ['required', 'exists:customers,id'],
            'invoice_date'    => ['required', 'date'],

            // تحديث طريقة الدفع لتقبل نوع الشبكة/الدفع الإلكتروني (card)
            'payment_type'    => ['required', Rule::in(['cash', 'card', 'credit'])],

            'subtotal'        => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount'      => ['nullable', 'numeric', 'min:0'],
            'grand_total'     => ['required', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'designer_id'          => ['nullable', Rule::exists('users', 'id')->where('type', 'designer')],
            'designer_meter_price' => ['nullable', 'numeric', 'min:0'],
            'design_commission'    => ['nullable', 'numeric', 'min:0'],
            'sale_type'            => ['required', Rule::in(['indoor', 'outdoor'])],
            'customer_name_text'   => ['nullable', 'string', 'max:255'],

            // --- قواعد مصفوفة السطور (Items) بناءً على معمارية الوحدات المحدثة ---
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.item_id'        => ['required', 'exists:items,id'],
            'items.*.item_unit_id'   => ['required', 'exists:item_units,id'],
            'items.*.quantity'       => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.subtotal'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount'=> ['nullable', 'numeric', 'min:0'],
            'items.*.grand_total'    => ['required', 'numeric', 'min:0'],
            'items.*.length'         => ['nullable', 'numeric', 'min:0'],
            'items.*.width'          => ['nullable', 'numeric', 'min:0'],
            'items.*.is_designed'    => ['required', 'boolean'],
        ];
    }

    /**
     * التحقق المتقدم بعد القواعد الأساسية لفرض الطول والعرض للأصناف المترية ومنع التلاعب بالمقاسات أثناء التحديث
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (empty($items) || !is_array($items)) {
                return;
            }

            // 1. فحص وفرض الطول والعرض للأصناف المترية ذات الأبعاد
            $itemIds = collect($items)->pluck('item_id')->filter()->unique()->toArray();
            $dimensionalItemIds = Item::whereIn('id', $itemIds)
                ->where('is_dimensional', true)
                ->pluck('id')
                ->toArray();

            foreach ($items as $index => $item) {
                $itemId = $item['item_id'] ?? null;

                if (in_array($itemId, $dimensionalItemIds)) {
                    $length = $item['length'] ?? null;
                    $width = $item['width'] ?? null;

                    if ($length === null || !is_numeric($length) || $length <= 0) {
                        $validator->errors()->add("items.{$index}.length", "حقل الطول مطلوب ويجب أن يكون أكبر من صفر لأن هذا الصنف متري الأبعاد.");
                    }

                    if ($width === null || !is_numeric($width) || $width <= 0) {
                        $validator->errors()->add("items.{$index}.width", "حقل العرض مطلوب ويجب أن يكون أكبر من صفر لأن هذا الصنف متري الأبعاد.");
                    }
                }
            }

            // 2. الرقابة الصارمة أثناء التحديث: التحقق من الكميات والمقاسات مع استثناء المستند الحالي
            $parentId = $this->input('parent_id');
            $invoiceType = $this->input('invoice_type');
            $currentSale = $this->route('sale'); // جلب المستند الحالي من المسار (Route)

            if ($invoiceType === 'return' && $parentId && $currentSale) {
                $parentSale = Sale::with('items.item')->find($parentId);

                if ($parentSale) {
                    // التعديل الجوهري: جلب المرتجعات السابقة مع استبعاد المرتجع الحالي تماماً من الحساب
                    $previousReturns = Sale::where('parent_id', $parentId)
                        ->where('invoice_type', 'return')
                        ->where('id', '!=', $currentSale->id) // استثناء المستند الحالي لمنع اصطدام الحسابات
                        ->with('items')
                        ->get();

                    // تجميع إجمالي الكميات والمساحات المترية الأصلية المتاحة بالفاتورة الأم
                    $originalTotals = [];
                    foreach ($parentSale->items as $pItem) {
                        $key = $pItem->item_id . '-' . $pItem->item_unit_id;
                        if (!isset($originalTotals[$key])) {
                            $originalTotals[$key] = [
                                'quantity' => 0.00,
                                'total_dimensional_qty' => 0.00,
                                'is_dimensional' => (bool)($pItem->item->is_dimensional ?? false)
                            ];
                        }
                        $originalTotals[$key]['quantity'] += (float)$pItem->quantity;

                        $pCalcQty = (bool)($pItem->item->is_dimensional ?? false)
                            ? ((float)$pItem->length * (float)$pItem->width * (float)$pItem->quantity)
                            : (float)$pItem->quantity;

                        $originalTotals[$key]['total_dimensional_qty'] += $pCalcQty;
                    }

                    // تجميع إجمالي ما تم إرجاعه مسبقاً في المستندات الأخرى فقط
                    $returnedTotals = [];
                    foreach ($previousReturns as $prevReturn) {
                        foreach ($prevReturn->items as $rItem) {
                            $key = $rItem->item_id . '-' . $rItem->item_unit_id;
                            if (!isset($returnedTotals[$key])) {
                                $returnedTotals[$key] = [
                                    'quantity' => 0.00,
                                    'total_dimensional_qty' => 0.00
                                ];
                            }
                            $returnedTotals[$key]['quantity'] += (float)$rItem->quantity;

                            $isDim = $originalTotals[$key]['is_dimensional'] ?? false;
                            $rCalcQty = $isDim
                                ? ((float)$rItem->length * (float)$rItem->width * (float)$rItem->quantity)
                                : (float)$rItem->quantity;

                            $returnedTotals[$key]['total_dimensional_qty'] += $rCalcQty;
                        }
                    }

                    // مطابقة عناصر طلب التعديل الحالي ومقارنتها بالمتبقي الفعلي المتاح
                    foreach ($items as $index => $item) {
                        $itemId = $item['item_id'] ?? null;
                        $itemUnitId = $item['item_unit_id'] ?? null;
                        $key = $itemId . '-' . $itemUnitId;

                        if (!isset($originalTotals[$key])) {
                            $validator->errors()->add("items.{$index}.item_id", "هذا الصنف غير موجود في سطور فاتورة المبيعات الأصلية المرجعية.");
                            continue;
                        }

                        $origQty = $originalTotals[$key]['quantity'];
                        $origDimQty = $originalTotals[$key]['total_dimensional_qty'];

                        $prevQty = $returnedTotals[$key]['quantity'] ?? 0.00;
                        $prevDimQty = $returnedTotals[$key]['total_dimensional_qty'] ?? 0.00;

                        $currentQty = (float)($item['quantity'] ?? 0.00);
                        $isDim = $originalTotals[$key]['is_dimensional'];

                        if ($isDim) {
                            $currentLength = (float)($item['length'] ?? 0.00);
                            $currentWidth = (float)($item['width'] ?? 0.00);
                            $currentDimQty = $currentLength * $currentWidth * $currentQty;

                            $availableDimQty = $origDimQty - $prevDimQty;

                            if ($currentDimQty > $availableDimQty) {
                                $validator->errors()->add("items.{$index}.quantity", "المساحة المترية المحدثة الحالية ({$currentDimQty} م²) تتجاوز المساحة المتبقية القابلة للإرجاع في الفاتورة الأصلية ({$availableDimQty} م²).");
                            }
                        } else {
                            $availableQty = $origQty - $prevQty;
                            if ($currentQty > $availableQty) {
                                $validator->errors()->add("items.{$index}.quantity", "الكمية المحدثة الحالية ({$currentQty}) تتجاوز الكمية المتبقية القابلة للإرجاع في الفاتورة الأصلية ({$availableQty}).");
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * تخصيص أسماء الحقول لرسائل الخطأ العربية الواضحة
     */
    public function attributes(): array
    {
        return [
            'invoice_type'             => 'نوع الفاتورة',
            'parent_id'                => 'فاتورة المبيعات الأصلية',
            'store_id'                 => 'المخزن',
            'treasury_id'              => 'الخزنة المالية المستلمة',
            'bank_id'                  => 'الحساب البنكي المستلم',
            'customer_id'              => 'حساب العميل',
            'invoice_date'             => 'تاريخ الفاتورة',
            'payment_type'             => 'طريقة الدفع',
            'grand_total'              => 'الصافي النهائي للمبيعات',
            'items'                    => 'عناصر الفاتورة',
            'items.*.item_id'          => 'الصنف المباع',
            'items.*.item_unit_id'     => 'وحدة الصنف',
            'items.*.quantity'         => 'الكمية المباعة',
            'items.*.unit_price'       => 'سعر البيع للوحدة',
            'items.*.grand_total'      => 'إجمالي السطر',
            'designer_id'              => 'المصمم المسؤول',
            'designer_meter_price'     => 'سعر المتر للمصمم',
            'design_commission'        => 'إجمالي عمولة التصميم',
            'sale_type'                => 'نوع حركة البيع (داخلي / خارجي)',
            'customer_name_text'       => 'اسم العميل الخارجي (النصي)',
            'items.*.is_designed'      => 'خاضع للتصميم في السطر',
            'items.*.length'           => 'الطول للسطر',
            'items.*.width'            => 'العرض للسطر',
        ];
    }
}
