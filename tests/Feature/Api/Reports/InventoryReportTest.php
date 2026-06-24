<?php

namespace Tests\Feature\Api\Reports;

use Tests\ApiTestCase;
use App\Models\Store;
use App\Models\Item;
use App\Models\Unit;
use App\Models\ItemUnit;
use App\Models\User;
use App\Models\ItemStock;
use App\Models\ItemMovement;
use App\Models\StockAdjustment;
use App\Models\Account;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryReportTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Item $item;
    protected Unit $unit1;
    protected Unit $unit2;
    protected ItemUnit $itemUnit1;
    protected ItemUnit $itemUnit2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد بيئة المصادقة وتخطي الصلاحيات للمشرف العام
        $this->user = User::factory()->create(['email' => 'admin_report@test.com']);
        Sanctum::actingAs($this->user);

        Gate::before(function ($user, $ability) {
            return $user->email === 'admin_report@test.com' ? true : null;
        });

        // 2. التأسيس المركزي للحسابات السيادية لمنع قيد التكرار المقيت (UNIQUE Constraint Error) نهائياً
        Account::firstOrCreate(
            ['code' => Account::CODE_INVENTORY],
            ['name' => 'مخزون المستودعات الرئيسي', 'type' => 'system', 'opening_balance' => 0, 'current_balance' => 0]
        );
        Account::firstOrCreate(
            ['code' => Account::CODE_SHORTAGE_EXPENSE],
            ['name' => 'تكلفة عجز تسويات المخزون', 'type' => 'expense', 'opening_balance' => 0, 'current_balance' => 0]
        );
        Account::firstOrCreate(
            ['code' => Account::CODE_SURPLUS_INCOME],
            ['name' => 'إيرادات فائض تسويات المخزون', 'type' => 'income', 'opening_balance' => 0, 'current_balance' => 0]
        );

        // 3. التأسيس اللوجستي للوحدات والمستودع
        $this->unit1 = Unit::create(['name' => 'حبة', 'short_name' => 'pcs', 'is_active' => true]);
        $this->unit2 = Unit::create(['name' => 'صندوق', 'short_name' => 'box', 'is_active' => true]);

        $this->store = Store::factory()->create(['name' => 'مخزن الجملة الرئيسي']);

        // 4. إنشاء الصنف بالاعتماد على حقل الوحدة القياسية الافتراضية base_unit_id
        $this->item = Item::create([
            'name'          => 'مشروب غازي كولا',
            'item_type'     => 'product',
            'profit_margin' => 20.00,
            'base_unit_id'  => $this->unit1->id,
            'is_active'     => true
        ]);

        // 5. بناء سطر الوحدة الأولى (الأساسية) داخل مصفوفة الوحدات والأسعار المحدثة
        $this->itemUnit1 = ItemUnit::create([
            'item_id'           => $this->item->id,
            'unit_id'           => $this->unit1->id,
            'conversion_factor' => 1.0000,
            'cost'              => 1.00,
            'price'             => 1.50,
        ]);

        // 6. بناء سطر الوحدة الثانية (الفرعية) داخل مصفوفة الوحدات بمعامل تحويل 24 حبة
        $this->itemUnit2 = ItemUnit::create([
            'item_id'           => $this->item->id,
            'unit_id'           => $this->unit2->id,
            'conversion_factor' => 24.0000,
            'cost'              => 24.00,
            'price'             => 30.00,
        ]);

        // تهيئة الرصيد اللحظي الافتراضي بـ 1200 حبة (وهي تعادل 50 صندوقاً بالتمام والكمال)
        ItemStock::create([
            'item_id'          => $this->item->id,
            'store_id'         => $this->store->id,
            'current_quantity' => 1200.0000
        ]);

        // ضخ حركتين مخزنيتين تاريخيتين في الجدول المحدث بالاعتماد على معرف مصفوفة الوحدات item_unit_id
        ItemMovement::create([
            'item_id'        => $this->item->id,
            'store_id'       => $this->store->id,
            'item_unit_id'   => $this->itemUnit2->id,
            'movement_type'  => 'purchase',
            'document_no'    => 'PINV-1001',
            'unit_name_used' => 'صندوق',
            'quantity'       => 60.00,
            'unit_factor'    => 24.00,
            'base_quantity'  => 1440.00,
            'cost_price'     => 24.00,
            'user_id'        => $this->user->id
        ]);

        ItemMovement::create([
            'item_id'        => $this->item->id,
            'store_id'       => $this->store->id,
            'item_unit_id'   => $this->itemUnit2->id,
            'movement_type'  => 'sales',
            'document_no'    => 'SINV-2001',
            'unit_name_used' => 'صندوق',
            'quantity'       => -10.00,
            'unit_factor'    => 24.00,
            'base_quantity'  => -240.00,
            'cost_price'     => 24.00,
            'user_id'        => $this->user->id
        ]);
    }

    /**
     * 1. اختبار تقرير الأرصدة اللحظية وتفكيك الكميات حركياً حسب الوحدات
     */
    public function test_inventory_current_stock_report(): void
    {
        $response = $this->getJson('/api/reports/inventory/current-stock?store_id=' . $this->store->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'stock_id',
                        'store_name',
                        'item_id',
                        'item_name',
                        'current_quantity_base',
                        'unit1_name',
                        'has_unit2',
                        'qty_in_unit2',
                        'unit2_name',
                        'unit_cost',
                        'total_cost_value',
                        'units_breakdown'
                    ]
                ]
            ]);

        // التأكد من صحة معادلة فك الكميات للوحدة الثانية (1200 حبة أساسية / 24 حبة = 50 صندوق) والتكلفة الكلية المحدثة
        $response->assertJsonFragment([
            'current_quantity_base' => 1200.00,
            'qty_in_unit2'          => 50.00,
            'total_cost_value'      => 1200.00
        ]);
    }

    /**
     * 2. اختبار كارت حركة الصنف التفصيلي واحتساب الأرصدة التراكمية الجارية
     */
    public function test_inventory_stock_card_report(): void
    {
        $response = $this->getJson('/api/reports/inventory/stock-card?item_id=' . $this->item->id . '&store_id=' . $this->store->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'item_id'         => $this->item->id,
                    'opening_balance' => 0.00,
                    'closing_balance' => 1200.00
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(1440.00, $response->json('data.0.running_balance'));
        $this->assertEquals(1200.00, $response->json('data.1.running_balance'));
    }

    /**
     * 3. اختبار تقرير التقييم المالي الرأسمالي للبضاعة المتوفرة بالمستودعات
     */
    public function test_inventory_valuation_report(): void
    {
        // تصحيح المسار المعماري: إعادة اللاحقة إلى /valuation لمنع الـ 404 والمطابقة مع نظام مبيعاتك الصارم
        $response = $this->getJson('/api/reports/inventory/valuation?store_id=' . $this->store->id);

        $response->assertStatus(200)
            ->assertJson([
                'success'               => true,
                'total_inventory_value' => 1200.00 // 1200 حبة * 1.00 تكلفة حبة أساسية من المصفوفة
            ]);
    }

    /**
     * 4. اختبار تقرير ملخص فروقات وعجز تسويات الجرد الميداني
     */
    public function test_inventory_adjustments_summary_report(): void
    {
        // التعديل المعماري: تم سحب كود الـ Account::factory تفادياً للتعارض والـ Unique constraint المنهار، حيث أصبحت الحسابات مشحونة مركزياً

        // بناء مستند تسوية جردية؛ ستقوم الـ boot method بتوليد الرقم المتسلسل تلقائياً بشكل آمن وحتمي
        $adjustment = StockAdjustment::create([
            'store_id'        => $this->store->id,
            'adjustment_date' => now(),
            'user_id'         => $this->user->id
        ]);

        $adjustment->items()->create([
            'item_id'             => $this->item->id,
            'item_unit_id'        => $this->itemUnit1->id, // استخدام معرف المصفوفة لوحدة الحبة
            'book_quantity'       => 1200.00,
            'physical_quantity'   => 1176.00, // عجز بمقدار 24 حبة
            'quantity_difference' => -24.00,
            'unit_cost'           => 1.00
        ]);

        $response = $this->getJson('/api/reports/inventory/adjustments-summary?store_id=' . $this->store->id);

        $response->assertStatus(200)
            ->assertJson([
                'success'              => true,
                'grand_total_shortage' => 24.00,
                'grand_total_surplus'  => 0.00
            ]);

        $response->assertJsonFragment([
            'adjustment_number' => $adjustment->adjustment_number,
            'shortage_value'    => 24.00
        ]);
    }
}
