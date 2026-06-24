<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SaleItem;
use App\Models\Item;

class SwapRawMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'items'                => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'exists:sale_items,id'],
            'items.*.item_id'      => [
                'required',
                'exists:items,id',
                function ($attribute, $value, $fail) {
                    // استخراج مؤشر المصفوفة ديناميكياً لمعرفة السطر الحالي (مثال: items.0.item_id)
                    preg_match('/items\.(\d+)\.item_id/', $attribute, $matches);
                    $index = $matches[1] ?? null;

                    if ($index !== null) {
                        $saleItemId = $this->input("items.{$index}.sale_item_id");

                        // جلب السطر القديم مع الصنف التابع له، وجلب بيانات الصنف البديل الجديد
                        $saleItem = SaleItem::with('item')->find($saleItemId);
                        $newItem = Item::find($value);

                        if ($saleItem && $newItem) {
                            // حماية توازن الأبعاد الهندسي: منع تداخل الأصناف المترية مع العددية الثابتة
                            if ($saleItem->item->is_dimensional !== $newItem->is_dimensional) {
                                if ($saleItem->item->is_dimensional) {
                                    $fail("لا يمكن استبدال الخامة المترية الحالية '{$saleItem->item->name}' بخامة عددية غير مترية، لتجنب كراش الحسابات المخزنية بالورشة.");
                                } else {
                                    $fail("لا يمكن استبدال الخامة العددية الحالية '{$saleItem->item->name}' بخامة مترية تعتمد على الأبعاد، لعدم وجود مقاسات مسجلة للسطر.");
                                }
                            }
                        }
                    }
                }
            ],
        ];
    }

    /**
     * رسائل الخطأ الأساسية بالعربية
     */
    public function messages(): array
    {
        return [
            'items.required'                => 'مصفوفة العناصر والتفاصيل مطلوبة لتحديث خامات المستند.',
            'items.array'                   => 'بنية تفاصيل الأصناف الممررة غير صحيحة.',
            'items.*.sale_item_id.required' => 'معرف سطر تفاصيل الفاتورة مطلوب لكل عنصر.',
            'items.*.sale_item_id.exists'   => 'سطر تفاصيل الفاتورة المحدد غير موجود بالنظام.',
            'items.*.item_id.required'      => 'يجب تحديد الخامة البديلة للسطر.',
            'items.*.item_id.exists'        => 'الخامة المختارة غير موجودة في دليل الأصناف.',
        ];
    }
}
