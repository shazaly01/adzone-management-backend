<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Account;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleTest extends ApiTestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد بيئة المصادقة والصلاحيات للمشرف العام عبر المنظومة الموحدة Sanctum
        $this->user = User::factory()->create(['email' => 'admin@test.com']);
        Sanctum::actingAs($this->user);

        // تطبيق شرط تخطي الصلاحيات للمشرف العام دون التأثير على فحص المستخدمين العاديين
        Gate::before(function ($user, $ability) {
            return $user->email === 'admin@test.com' ? true : null;
        });

        // 2. التأسيس الجذري لقاعدة البيانات: زرع الحسابات السيادية مع تحديد الطبيعة المحاسبية (nature) صراحة منعاً للتخمين
        Account::firstOrCreate(
            ['code' => Account::CODE_CUSTOMERS],
            ['name' => 'حساب العملاء الإجمالي', 'type' => 'customer', 'opening_balance' => 0, 'current_balance' => 0, 'nature' => 'debit']
        );
        Account::firstOrCreate(
            ['code' => Account::CODE_INCOME],
            ['name' => 'حساب الإيرادات التشغيلية', 'type' => 'income', 'opening_balance' => 0, 'current_balance' => 0, 'nature' => 'credit']
        );
        Account::firstOrCreate(
            ['code' => Account::CODE_TREASURY],
            ['name' => 'حساب الخزائن الرئيسي', 'type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'nature' => 'debit']
        );
        Account::firstOrCreate(
            ['code' => Account::CODE_INVENTORY],
            ['name' => 'المخزون الرئيسي', 'type' => 'system', 'opening_balance' => 0, 'current_balance' => 0, 'nature' => 'debit']
        );
        Account::firstOrCreate(
            ['code' => Account::CODE_COGS],
            ['name' => 'حساب تكلفة البضاعة والسلع المباعة', 'type' => 'expense', 'opening_balance' => 0, 'current_balance' => 0, 'nature' => 'debit']
        );
    }

    /**
     * =========================================================================
     * 1. اختبارات المسارات الناجحة (Happy Paths)
     * =========================================================================
     */

    public function test_can_list_sales(): void
    {
        Sale::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/sales');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'invoice_type',
                        'invoice_type_lbl',
                        'invoice_sequence',
                        'invoice_number',
                        'parent_id',
                        'parent_number',
                        'store_id',
                        'store_name',
                        'customer_id',
                        'customer_name',
                        'user_id',
                        'user_name',
                        'invoice_date',
                        'payment_type',
                        'payment_type_lbl',
                        'subtotal',
                        'discount_amount',
                        'tax_amount',
                        'grand_total',
                        'notes'
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_create_cash_sale_invoice_successfully(): void
    {
        $store = Store::factory()->create();
        $customer = Customer::factory()->create();

        // التعديل المعماري: إنشاء الصنف وتعديل تكلفته التأسيسية مباشرة داخل جدول المصفوفة الوسيط لحساب الـ COGS بدقة
        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();
        $itemUnit->update(['cost' => 200.00, 'price' => 300.00]);

        $payload = [
            'invoice_type'    => 'sale',
            'store_id'        => $store->id,
            'customer_id'     => $customer->id,
            'invoice_date'    => now()->format('Y-m-d H:i:s'),
            'payment_type'    => 'cash',
            'subtotal'        => 1500.00,
            'discount_amount' => 0.00,
            'tax_amount'      => 225.00,
            'grand_total'     => 1725.00,
            'notes'           => 'فاتورة مبيعات نقدية تجريبية متكاملة',
            'items' => [
                [
                    'item_id'         => $item->id,
                    // التعديل المعماري: تمرير حقل معرف سطر المصفوفة المطور
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => 5.00,
                    'unit_price'      => 300.00,
                    'subtotal'        => 1500.00,
                    'discount_amount' => 0.00,
                    'grand_total'     => 1500.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'تم حفظ فاتورة المبيعات وتحديث حسابات العميل والصندوق والمخازن بنجاح.'
            ]);

        $this->assertDatabaseHas('sales', [
            'store_id'     => $store->id,
            'customer_id'  => $customer->id,
            'invoice_type' => 'sale',
            'payment_type' => 'cash',
            'grand_total'  => 1725.00,
        ]);

        $sale = Sale::first();
        $this->assertNotNull($sale->invoice_number);
        $this->assertNotNull($sale->journal_entry_id);

        $this->assertDatabaseHas('sale_items', [
            'sale_id'      => $sale->id,
            'item_id'      => $item->id,
            'item_unit_id' => $itemUnit->id,
            'quantity'     => 5.00,
        ]);

        $this->assertDatabaseHas('item_movements', [
            'item_id'       => $item->id,
            'store_id'      => $store->id,
            'movement_type' => 'sales',
            'document_no'   => $sale->invoice_number,
            'quantity'      => -5.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id'           => $sale->journal_entry_id,
            'entry_number' => $sale->invoice_number,
        ]);
    }

    public function test_can_create_credit_sale_invoice_successfully(): void
    {
        $store = Store::factory()->create();
        $customer = Customer::factory()->create();
        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();

        $payload = [
            'invoice_type'    => 'sale',
            'store_id'        => $store->id,
            'customer_id'     => $customer->id,
            'invoice_date'    => now()->format('Y-m-d H:i:s'),
            'payment_type'    => 'credit',
            'subtotal'        => 1000.00,
            'discount_amount' => 0.00,
            'tax_amount'      => 150.00,
            'grand_total'     => 1150.00,
            'notes'           => 'فاتورة مبيعات آجلة تجريبية',
            'items' => [
                [
                    'item_id'         => $item->id,
                    // التعديل المعماري: حقن حقل المصفوفة المستقر بدلاً من الوحدة العامة
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => 2.00,
                    'unit_price'      => 500.00,
                    'subtotal'        => 1000.00,
                    'discount_amount' => 0.00,
                    'grand_total'     => 1000.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'invoice_type' => 'sale',
            'payment_type' => 'credit',
            'grand_total'  => 1150.00,
        ]);
    }

    public function test_can_create_sale_return_invoice_successfully(): void
    {
        $parentSale = Sale::factory()->create([
            'invoice_type' => 'sale',
            'user_id'      => $this->user->id,
            'invoice_date' => now() // إجبار تاريخ الإنشاء ليكون اليوم لتخطي فحص التواريخ التاريخية
        ]);
        $store = Store::factory()->create();
        $customer = Customer::factory()->create();
        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();

        $payload = [
            'invoice_type'    => 'return',
            'parent_id'       => $parentSale->id,
            'store_id'        => $store->id,
            'customer_id'     => $customer->id,
            'invoice_date'    => now()->format('Y-m-d H:i:s'),
            'payment_type'    => 'cash',
            'subtotal'        => 300.00,
            'discount_amount' => 0.00,
            'tax_amount'      => 45.00,
            'grand_total'     => 345.00,
            'notes'           => 'مردودات مبيعات تجريبية بقيم كمية موجبة للمخزن',
            'items' => [
                [
                    'item_id'         => $item->id,
                    // التعديل المعماري: ربط مرتجع المبيعات بسجل المصفوفة الصحيح
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => 1.00,
                    'unit_price'      => 300.00,
                    'subtotal'        => 300.00,
                    'discount_amount' => 0.00,
                    'grand_total'     => 300.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(201);

        $returnInvoice = Sale::where('invoice_type', 'return')->first();
        $this->assertNotNull($returnInvoice);

        $this->assertDatabaseHas('item_movements', [
            'document_no' => $returnInvoice->invoice_number,
            'quantity'    => 1.00,
        ]);
    }

    public function test_can_show_sale_invoice_details_with_items(): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson("/api/sales/{$sale->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'             => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'items'
                ]
            ]);
    }

    public function test_can_update_sale_invoice_successfully(): void
    {
        $sale = Sale::factory()->create([
            'user_id'      => $this->user->id,
            'invoice_date' => now() // حماية: تفادي قفل القيود التاريخية التشغيلية أثناء الفحص
        ]);
        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();

        $updatePayload = [
            'invoice_type'    => 'sale',
            'store_id'        => $sale->store_id,
            'customer_id'     => $sale->customer_id,
            'invoice_date'    => $sale->invoice_date->format('Y-m-d H:i:s'),
            'payment_type'    => 'cash',
            'subtotal'        => 600.00,
            'discount_amount' => 0.00,
            'tax_amount'      => 90.00,
            'grand_total'     => 690.00,
            'notes'           => 'تم تحديث فاتورة المبيعات وعكس القيود السابقة حركياً',
            'items' => [
                [
                    'item_id'         => $item->id,
                    // التعديل المعماري: تمرير حقل معرف سطر المصفوفة لتجاوز فحص الـ Validator عند التعديل
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => 2.00,
                    'unit_price'      => 300.00,
                    'subtotal'        => 600.00,
                    'discount_amount' => 0.00,
                    'grand_total'     => 600.00,
                ]
            ]
        ];

        $response = $this->putJson("/api/sales/{$sale->id}", $updatePayload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales', [
            'id'          => $sale->id,
            'grand_total' => 690.00
        ]);
    }

    public function test_can_soft_delete_sale_invoice_and_reverse_all_effects(): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->deleteJson("/api/sales/{$sale->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('sales', [
            'id' => $sale->id
        ]);

        $this->assertDatabaseMissing('item_movements', [
            'document_no' => $sale->invoice_number
        ]);
    }

    /**
     * =========================================================================
     * 2. اختبارات التحقق من صحة المدخلات وفشلها (Unhappy Paths - Validation)
     * =========================================================================
     */

    public function test_cannot_create_sale_return_without_parent_id(): void
    {
        $store = Store::factory()->create();
        $customer = Customer::factory()->create();

        $payload = [
            'invoice_type' => 'return',
            'parent_id'    => null,
            'store_id'     => $store->id,
            'customer_id'  => $customer->id,
            'invoice_date' => now()->format('Y-m-d H:i:s'),
            'payment_type' => 'cash',
            'subtotal'     => 100.00,
            'grand_total'  => 100.00,
            'items'        => []
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_cannot_create_sale_with_zero_or_negative_quantity(): void
    {
        $store = Store::factory()->create();
        $customer = Customer::factory()->create();
        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();

        $payload = [
            'invoice_type'    => 'sale',
            'store_id'        => $store->id,
            'customer_id'     => $customer->id,
            'invoice_date'    => now()->format('Y-m-d H:i:s'),
            'payment_type'    => 'cash',
            'subtotal'        => 500.00,
            'grand_total'     => 500.00,
            'items' => [
                [
                    'item_id'         => $item->id,
                    // التعديل المعماري: فحص الفشل اللوجستي للكميات السالبة مع المعرف الجديد للوحدة
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => -1.00,
                    'unit_price'      => 500.00,
                    'subtotal'        => 500.00,
                    'grand_total'     => 500.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    /**
     * =========================================================================
     * 3. اختبارات حماية الصلاحيات والمنافذ الجانبية (Security & Authorization)
     * =========================================================================
     */

    public function test_unauthorized_user_cannot_list_sales(): void
    {
        $regularUser = User::factory()->create(['email' => 'regular_list_cashier@test.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/sales');

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_create_sale_invoice(): void
    {
        $regularUser = User::factory()->create(['email' => 'regular_create_cashier@test.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->postJson('/api/sales', [
            'invoice_type' => 'sale'
        ]);

        $response->assertStatus(403);
    }
}
