<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;
use App\Models\OpeningStock;
use App\Models\Store;
use App\Models\Item;
use App\Models\Unit;
use App\Models\ItemUnit;
use App\Models\Account;
use App\Models\User;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OpeningStockTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Item $item;
    protected Unit $unit;
    protected ItemUnit $itemUnit;
    protected Account $inventoryAccount;
    protected Account $assetCapitalAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد بيئة المصادقة والصلاحيات للمشرف العام عبر بوابات النظام
        $this->user = User::factory()->create(['email' => 'admin_opening@test.com']);
        Sanctum::actingAs($this->user);

        Gate::before(function ($user, $ability) {
            return $user->email === 'admin_opening@test.com' ? true : null;
        });

        if (class_exists(\Spatie\Permission\Models\Permission::class)) {
            \Spatie\Permission\Models\Permission::findOrCreate('opening_stock.view', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('opening_stock.create', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('opening_stock.update', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('opening_stock.delete', 'api');
        }

        // 2. بناء الكيانات اللوجستية الأساسية للمشروع بناءً على المعمارية المحدثة
        $this->unit = Unit::create([
            'name'       => 'حبة',
            'short_name' => 'pcs',
            'is_active'  => true
        ]);

        $this->store = Store::factory()->create(['name' => 'مستودع الأرصدة الافتتاحية']);

        $this->item = Item::create([
            'name'          => 'صنف تأسيسي تجريبي',
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
            'cost'              => 0.00, // نبدأ بتكلفة صفرية لفحص ميكانيكية التحديث التلقائي للتكلفة الافتتاحية الأولى
            'price'             => 115.00,
        ]);

        // 3. زرع وحماية الحسابات السيادية المطلوبة بالملي في خدمة بضاعة أول المدة
        $this->inventoryAccount = Account::firstOrCreate(
            ['code' => Account::CODE_INVENTORY],
            ['name' => 'مخزون المستودعات الرئيسي', 'type' => 'system', 'opening_balance' => 0, 'current_balance' => 0]
        );

        $this->assetCapitalAccount = Account::firstOrCreate(
            ['code' => '2202'],
            ['name' => 'حساب رأس مال الأصول الافتتاحية', 'type' => 'system', 'opening_balance' => 0, 'current_balance' => 0]
        );
    }

    /**
     * =========================================================================
     * 1. اختبارات المسارات الناجحة (Happy Paths)
     * =========================================================================
     */

    public function test_can_list_opening_stocks_with_pagination(): void
    {
        OpeningStock::create([
            'store_id'     => $this->store->id,
            'opening_date' => now(),
            'user_id'      => $this->user->id
        ]);

        $response = $this->getJson('/api/opening-stocks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'opening_number',
                        'store_id',
                        'store_name',
                        'opening_date',
                        'notes',
                        'journal_entry_id',
                        'journal_entry_no',
                        'user_id',
                        'user_name',
                        'created_at'
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_create_opening_stock_successfully_and_generate_journal(): void
    {
        $payload = [
            'store_id'     => $this->store->id,
            'opening_date' => now()->format('Y-m-d H:i:s'),
            'notes'        => 'تغذية أرصدة بضاعة أول المدة للعام الجديد تلقائياً',
            'items'        => [
                [
                    'item_id'      => $this->item->id,
                    // التعديل المعماري: حقن معرف المصفوفة بدلاً من معرف الوحدة العام
                    'item_unit_id' => $this->itemUnit->id,
                    'quantity'     => 50.00, // حقن 50 حبة
                    'unit_cost'    => 120.00, // تكلفة الحبة 120 (الإجمالي = 6000)
                ]
            ]
        ];

        $response = $this->postJson('/api/opening-stocks', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'تم حفظ مستند بضاعة أول المدة، وحقن الكميات بالمخازن وتوليد القيد التأسيسي بنجاح.'
            ]);

        // التحقق من تحديث جدول الكاش اللحظي للمخزون الفعلي ليصبح 50 حبة موجبة صريحة
        $this->assertDatabaseHas('item_stocks', [
            'item_id'          => $this->item->id,
            'store_id'         => $this->store->id,
            'current_quantity' => 50.0000
        ]);

        // التعديل المعماري: التحقق من تحديث كود تكلفة الصنف التأسيسي داخل جدول المصفوفة المحدث بعد أن كانت صفراً
        $this->itemUnit->refresh();
        $this->assertEquals(120.00, (float) $this->itemUnit->cost);

        // التحقق من وجود رأس مستند بضاعة أول المدة برقم متسلسل صحيح
        $document = OpeningStock::first();
        $this->assertNotNull($document);
        $this->assertNotNull($document->journal_entry_id);

        // التحقق من توازن وصحة القيد المحاسبي التلقائي في الدفاتر الشاملة
        $this->assertDatabaseHas('journal_entries', [
            'id'           => $document->journal_entry_id,
            'entry_number' => $document->opening_number,
        ]);

        // فحص قيود اليومية التفصيلية (مدين للمخزن بـ 6000 ودائن لرأس مال الأصول بـ 6000)
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $document->journal_entry_id,
            'account_id'       => $this->inventoryAccount->id,
            'debit'            => 6000.00,
            'credit'           => 0.00
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $document->journal_entry_id,
            'account_id'       => $this->assetCapitalAccount->id,
            'debit'            => 0.00,
            'credit'           => 6000.00
        ]);
    }

    public function test_can_show_opening_stock_details_with_item_lines(): void
    {
        $document = OpeningStock::create([
            'store_id'     => $this->store->id,
            'opening_date' => now(),
            'user_id'      => $this->user->id
        ]);

        $document->items()->create([
            'item_id'      => $this->item->id,
            // التعديل المعماري: تعويض العمود بقاعدة البيانات إلى معرف المصفوفة المحدث
            'item_unit_id' => $this->itemUnit->id,
            'quantity'     => 10,
            'unit_cost'    => 100,
            'subtotal'     => 1000
        ]);

        $response = $this->getJson("/api/opening-stocks/{$document->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'opening_number',
                    'items' => [
                        '*' => ['id', 'item_name', 'quantity', 'unit_cost', 'subtotal']
                    ]
                ]
            ]);
    }

    public function test_can_update_opening_stock_and_recalculate_all_effects(): void
    {
        // 1. إنشاء مستند أولي بقيمة 10 حبات × 100 تكلفة = 1000 إجمالي
        $payload = [
            'store_id'     => $this->store->id,
            'opening_date' => now()->format('Y-m-d H:i:s'),
            'notes'        => 'ميزانية الرصيد الابتدائي الأولية',
            'items'        => [
                [
                    'item_id'      => $this->item->id,
                    'item_unit_id' => $this->itemUnit->id,
                    'quantity'     => 10.00,
                    'unit_cost'    => 100.00,
                ]
            ]
        ];

        $this->postJson('/api/opening-stocks', $payload);
        $document = OpeningStock::first();

        // 2. تحديث المستند ليصبح 5 حبات × 200 تكلفة = 1000 إجمالي القيد، مع تغير الكمية في المخازن إلى 5
        $updatePayload = [
            'store_id'     => $this->store->id,
            'opening_date' => now()->format('Y-m-d H:i:s'),
            'notes'        => 'تعديل وتثبيت الرصيد الابتدائي النهائي للتدقيق والمطابقة والموافقة',
            'items'        => [
                [
                    'item_id'      => $this->item->id,
                    // التعديل المعماري: تمرير المعرف المتطابق لتمرير الطلب بنجاح عبر الـ Request Validator المحدث
                    'item_unit_id' => $this->itemUnit->id,
                    'quantity'     => 5.00,
                    'unit_cost'    => 200.00,
                ]
            ]
        ];

        $response = $this->putJson("/api/opening-stocks/{$document->id}", $updatePayload);

        $response->assertStatus(200);

        // فحص انزياح الحركة القديمة وحقن رصيد الكاش اللحظي الجديد ليصبح 5 حبات صريحة بالتمام
        $this->assertDatabaseHas('item_stocks', [
            'item_id'          => $this->item->id,
            'store_id'         => $this->store->id,
            'current_quantity' => 5.0000
        ]);
    }

    public function test_can_soft_delete_opening_stock_and_wipe_all_journal_lines(): void
    {
        $payload = [
            'store_id'     => $this->store->id,
            'opening_date' => now()->format('Y-m-d H:i:s'),
            'items'        => [
                [
                    'item_id'      => $this->item->id,
                    'item_unit_id' => $this->itemUnit->id,
                    'quantity'     => 30.00,
                    'unit_cost'    => 100.00,
                ]
            ]
        ];

        $this->postJson('/api/opening-stocks', $payload);
        $document = OpeningStock::first();
        $oldJournalId = $document->journal_entry_id;

        // تنفيذ عملية الحذف الناعم الحِمائي
        $response = $this->deleteJson("/api/opening-stocks/{$document->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('opening_stocks', ['id' => $document->id]);

        // التحقق من تصفية وإلغاء حركات المخزن الفورية ليعود رصيد الكاش صفراً
        $stock = ItemStock::where('item_id', $this->item->id)->where('store_id', $this->store->id)->first();
        $this->assertEquals(0.0000, $stock->current_quantity);

        // التحقق من مسح وإبادة القيد المالي المرتبط بالكامل من دفاتر اليومية لمنع تشويه ميزان المراجعة الافتتاحي
        $this->assertNull(JournalEntry::find($oldJournalId));
    }

    /**
     * =========================================================================
     * 2. اختبارات الفشل والتحقق من صحة البيانات (Unhappy Paths)
     * =========================================================================
     */

    public function test_cannot_create_opening_stock_with_duplicate_items_in_lines(): void
    {
        $payload = [
            'store_id'     => $this->store->id,
            'opening_date' => now()->format('Y-m-d H:i:s'),
            'items'        => [
                [
                    'item_id'      => $this->item->id,
                    'item_unit_id' => $this->itemUnit->id,
                    'quantity'     => 10.00,
                    'unit_cost'    => 100.00,
                ],
                [
                    'item_id'      => $this->item->id,
                    // التعديل المعماري: فحص دقة التقاط الخطأ لقاعدة التكرار (distinct) على مستوى السطر الثاني للمصفوفة المحدثة
                    'item_unit_id' => $this->itemUnit->id,
                    'quantity'     => 5.00,
                    'unit_cost'    => 100.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/opening-stocks', $payload);

        // التعديل المعماري: لارافيل تطلق رسالة خطأ التكرار على مفتاح السطر المكرر للوحدة تحديداً
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.item_unit_id']);
    }

    public function test_unauthorized_user_cannot_list_opening_stocks(): void
    {
        $regularUser = User::factory()->create(['email' => 'clerk_opening@test.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/opening-stocks');

        $response->assertStatus(403);
    }
}
