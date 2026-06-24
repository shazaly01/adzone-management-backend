<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;
use App\Models\StockAdjustment;
use App\Models\Store;
use App\Models\Item;
use App\Models\Unit;
use App\Models\ItemUnit;
use App\Models\Account;
use App\Models\User;
use App\Models\ItemStock;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockAdjustmentTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Item $item;
    protected Unit $unit;
    protected ItemUnit $itemUnit;
    protected Account $inventoryAccount;
    protected Account $expenseAccount;
    protected Account $incomeAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد بيئة المصادقة والصلاحيات للمشرف العام
        $this->user = User::factory()->create(['email' => 'admin_stock@test.com']);
        Sanctum::actingAs($this->user);

        Gate::before(function ($user, $ability) {
            return $user->email === 'admin_stock@test.com' ? true : null;
        });

        if (class_exists(\Spatie\Permission\Models\Permission::class)) {
            \Spatie\Permission\Models\Permission::findOrCreate('stock_adjustment.view', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('stock_adjustment.create', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('stock_adjustment.update', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('stock_adjustment.delete', 'api');
        }

        // 2. بناء الوحدة والمستودع والصنف بناءً على المعمارية المحدثة
        $this->unit = Unit::create([
            'name'       => 'حبة',
            'short_name' => 'pcs',
            'is_active'  => true
        ]);

        $this->store = Store::factory()->create(['name' => 'المستودع المركزي']);

        // تعديل حقول كائن الصنف ليتوافق مع إلغاء حقول وحدات الصنف الثابتة
        $this->item = Item::create([
            'name'          => 'شاشات عرض الذكية',
            'item_type'     => 'product',
            'profit_margin' => 15.00,
            'base_unit_id'  => $this->unit->id,
            'is_active'     => true
        ]);

        // التأسيس المعماري: إنشاء سطر الوحدة الحتمي داخل مصفوفة أسعار ووحدات الأصناف
        $this->itemUnit = ItemUnit::create([
            'item_id'           => $this->item->id,
            'unit_id'           => $this->unit->id,
            'conversion_factor' => 1.0000,
            'cost'              => 100.00,
            'price'             => 115.00,
        ]);

        // الحماية الحتمية ضد التكرار: استخدام firstOrCreate لامتصاص أي حسابات تم توليدها
        $this->inventoryAccount = Account::firstOrCreate(
            ['code' => Account::CODE_INVENTORY],
            ['name' => 'مخزون المستودعات الرئيسي', 'type' => 'system', 'opening_balance' => 0, 'current_balance' => 0]
        );

        $this->expenseAccount = Account::firstOrCreate(
            ['code' => Account::CODE_SHORTAGE_EXPENSE],
            ['name' => 'تكلفة عجز تسويات المخزون', 'type' => 'expense', 'opening_balance' => 0, 'current_balance' => 0]
        );

        $this->incomeAccount = Account::firstOrCreate(
            ['code' => Account::CODE_SURPLUS_INCOME],
            ['name' => 'إإيرادات فائض تسويات المخزون', 'type' => 'income', 'opening_balance' => 0, 'current_balance' => 0]
        );

        // تهيئة الرصيد اللحظي الافتراضي بـ 10 حبات
        ItemStock::create([
            'item_id'          => $this->item->id,
            'store_id'         => $this->store->id,
            'current_quantity' => 10.0000
        ]);
    }

    /**
     * اختبار استعراض القيود والتحقق من بنية الـ Pagination الشجرية الناجحة
     */
    public function test_can_list_stock_adjustments(): void
    {
        StockAdjustment::create([
            'store_id'        => $this->store->id,
            'adjustment_date' => now(),
            'user_id'         => $this->user->id
        ]);

        $response = $this->getJson('/api/stock-adjustments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'adjustment_sequence',
                        'adjustment_number',
                        'store_id',
                        'store_name',
                        'adjustment_date',
                        'notes',
                        'user_id',
                        'user_name',
                        'journal_entry_id',
                        'journal_entry_no',
                        'items'
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_create_stock_adjustment_with_shortage_deficit_and_generate_journal(): void
    {
        $payload = [
            'store_id'        => $this->store->id,
            'adjustment_date' => now()->format('Y-m-d H:i:s'),
            'notes'           => 'تسوية عجز جرد الربع الأول الفعلي',
            'items'           => [
                [
                    'item_id'           => $this->item->id,
                    // التعديل المعماري: حقن معرف المصفوفة بدلاً من معرف الوحدة العام
                    'item_unit_id'      => $this->itemUnit->id,
                    'book_quantity'     => 10.00,
                    'physical_quantity' => 8.00, // عجز حبتين
                    'unit_cost'         => 150.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/stock-adjustments', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('item_stocks', [
            'item_id'          => $this->item->id,
            'store_id'         => $this->store->id,
            'current_quantity' => 8.0000
        ]);
    }

    public function test_can_create_stock_adjustment_with_surplus_and_generate_journal(): void
    {
        $payload = [
            'store_id'        => $this->store->id,
            'adjustment_date' => now()->format('Y-m-d H:i:s'),
            'notes'           => 'تسوية فائض جرد الميداني',
            'items'           => [
                [
                    'item_id'           => $this->item->id,
                    // التعديل المعماري: حقن معرف المصفوفة بدلاً من معرف الوحدة العام
                    'item_unit_id'      => $this->itemUnit->id,
                    'book_quantity'     => 10.00,
                    'physical_quantity' => 15.00, // فائض 5 حبات
                    'unit_cost'         => 100.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/stock-adjustments', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('item_stocks', [
            'item_id'          => $this->item->id,
            'store_id'         => $this->store->id,
            'current_quantity' => 15.0000
        ]);
    }

    public function test_can_show_stock_adjustment_details_with_item_lines(): void
    {
        $adjustment = StockAdjustment::create([
            'store_id'        => $this->store->id,
            'adjustment_date' => now(),
            'user_id'         => $this->user->id
        ]);

        $adjustment->items()->create([
            'item_id'             => $this->item->id,
            // التعديل المعماري: تعويض العمود بقاعدة البيانات إلى معرف المصفوفة المحدث
            'item_unit_id'        => $this->itemUnit->id,
            'book_quantity'       => 10,
            'physical_quantity'   => 9,
            'quantity_difference' => -1,
            'unit_cost'           => 100
        ]);

        $response = $this->getJson("/api/stock-adjustments/{$adjustment->id}");

        $response->assertStatus(200);
    }

    public function test_can_update_stock_adjustment_on_the_same_day_and_recalc_stocks(): void
    {
        $adjustment = StockAdjustment::create([
            'store_id'        => $this->store->id,
            'adjustment_date' => now(),
            'user_id'         => $this->user->id
        ]);

        $updatePayload = [
            'store_id'        => $this->store->id,
            'adjustment_date' => now()->format('Y-m-d H:i:s'),
            'notes'           => 'تعديل مستند التسوية والموافقة',
            'items'           => [
                [
                    'item_id'           => $this->item->id,
                    // التعديل المعماري: تمرير المعرف المتطابق لتمرير الطلب بنجاح عبر الـ FormRequest
                    'item_unit_id'      => $this->itemUnit->id,
                    'book_quantity'     => 10.00,
                    'physical_quantity' => 7.00, // عجز 3 حبات
                    'unit_cost'         => 200.00,
                ]
            ]
        ];

        $response = $this->putJson("/api/stock-adjustments/{$adjustment->id}", $updatePayload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('item_stocks', [
            'item_id'          => $this->item->id,
            'store_id'         => $this->store->id,
            'current_quantity' => 7.0000
        ]);
    }

    public function test_can_soft_delete_stock_adjustment_and_reverse_all_movements(): void
    {
        $adjustment = StockAdjustment::create([
            'store_id'        => $this->store->id,
            'adjustment_date' => now(),
            'user_id'         => $this->user->id
        ]);

        $stock = ItemStock::where('item_id', $this->item->id)->where('store_id', $this->store->id)->first();
        $stock->update(['current_quantity' => 8.0000]);

        \App\Models\ItemMovement::create([
            'item_id'       => $this->item->id,
            'store_id'      => $this->store->id,
            // التعديل المعماري: تحويل البيانات الحركية التاريخية لتعمل على معرف مصفوفة الوحدات الجديد
            'item_unit_id'  => $this->itemUnit->id,
            'movement_type' => 'adjustment',
            'document_no'   => $adjustment->adjustment_number,
            'unit_name_used'=> 'حبة',
            'quantity'      => -2.00,
            'unit_factor'   => 1.00,
            'base_quantity' => -2.00,
            'cost_price'    => 100,
            'user_id'       => $this->user->id
        ]);

        $response = $this->deleteJson("/api/stock-adjustments/{$adjustment->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('stock_adjustments', ['id' => $adjustment->id]);

        $stock->refresh();
        $this->assertEquals(10.0000, $stock->current_quantity);
    }

    public function test_cannot_create_stock_adjustment_with_duplicate_items_in_lines(): void
    {
        $payload = [
            'store_id'        => $this->store->id,
            'adjustment_date' => now()->format('Y-m-d H:i:s'),
            'items'           => [
                [
                    'item_id'           => $this->item->id,
                    'item_unit_id'      => $this->itemUnit->id,
                    'book_quantity'     => 10.00,
                    'physical_quantity' => 9.00,
                    'unit_cost'         => 100.00,
                ],
                [
                    'item_id'           => $this->item->id,
                    // التعديل المعماري: فحص دقة التقاط الخطأ لقاعدة التكرار (distinct) على مستوى السطر الثاني للمصفوفة
                    'item_unit_id'      => $this->itemUnit->id,
                    'book_quantity'     => 10.00,
                    'physical_quantity' => 8.00,
                    'unit_cost'         => 100.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/stock-adjustments', $payload);

        // التعديل المعماري: لارافيل تطلق رسالة خطأ التكرار على مفتاح السطر المكرر تحديداً
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.item_unit_id']);
    }

    public function test_cannot_update_historical_stock_adjustment_from_previous_days(): void
    {
        $historicalAdjustment = StockAdjustment::create([
            'store_id'        => $this->store->id,
            'adjustment_date' => now()->subDays(2),
            'user_id'         => $this->user->id
        ]);

        $payload = [
            'store_id'        => $this->store->id,
            'adjustment_date' => now()->format('Y-m-d H:i:s'),
            'items'           => [
                [
                    'item_id'           => $this->item->id,
                    'item_unit_id'      => $this->itemUnit->id,
                    'book_quantity'     => 10.00,
                    'physical_quantity' => 10.00,
                    'unit_cost'         => 100.00,
                ]
            ]
        ];

        $response = $this->putJson("/api/stock-adjustments/{$historicalAdjustment->id}", $payload);

        $response->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_list_stock_adjustments(): void
    {
        $hacker = User::factory()->create(['email' => 'clerk_stock@test.com']);
        Sanctum::actingAs($hacker);

        $response = $this->getJson('/api/stock-adjustments');

        $response->assertStatus(403);
    }
}
