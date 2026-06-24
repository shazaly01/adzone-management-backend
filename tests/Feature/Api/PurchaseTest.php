<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Account;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseTest extends ApiTestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد بيئة المصادقة والصلاحيات لـ المشرف العام عبر المنظومة الموحدة Sanctum
        $this->user = User::factory()->create(['email' => 'admin@test.com']);
        Sanctum::actingAs($this->user);

        // تطبيق شرط تخطي الصلاحيات للمشرف العام دون التأثير على فحص المستخدمين العاديين
        Gate::before(function ($user, $ability) {
            return $user->email === 'admin@test.com' ? true : null;
        });

        // 2. التأسيس الجذري لقاعدة البيانات: زرع الحسابات السيادية للنظام لمرة واحدة
        Account::firstOrCreate(
            ['code' => Account::CODE_INVENTORY],
            ['name' => 'المخزون الرئيسي', 'type' => 'system', 'opening_balance' => 0, 'current_balance' => 0, 'nature' => 'debit']
        );
        Account::firstOrCreate(
            ['code' => Account::CODE_SUPPLIERS],
            ['name' => 'حساب الموردين الإجمالي', 'type' => 'supplier', 'opening_balance' => 0, 'current_balance' => 0, 'nature' => 'credit']
        );
    }

    /**
     * =========================================================================
     * 1. اختبارات المسارات الناجحة (Happy Paths)
     * =========================================================================
     */

    public function test_can_list_purchases(): void
    {
        Purchase::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/purchases');

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
                        'supplier_id',
                        'supplier_name',
                        'user_id',
                        'user_name',
                        'invoice_date',
                        'payment_type',
                        'payment_type_lbl',
                        'subtotal',
                        'discount_amount',
                        'tax_amount',
                        'grand_total',
                        'notes',
                        'items'
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_create_purchase_invoice_successfully(): void
    {
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->create();

        // المصنع المطور للصنف يقوم تلقائياً بإنشاء وحدة أساسية له في مصفوفة item_units
        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();

        $payload = [
            'invoice_type'    => 'purchase',
            'store_id'        => $store->id,
            'supplier_id'     => $supplier->id,
            'invoice_date'    => now()->format('Y-m-d H:i:s'),
            'payment_type'    => 'credit',
            'subtotal'        => 1000.00,
            'discount_amount' => 0.00,
            'tax_amount'      => 150.00,
            'grand_total'     => 1150.00,
            'notes'           => 'فاتورة اختبار آلية متكاملة',
            'items' => [
                [
                    'item_id'         => $item->id,
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => 10.00,
                    'unit_cost'       => 100.00,
                    'subtotal'        => 1000.00,
                    'discount_amount' => 0.00,
                    'grand_total'     => 1000.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/purchases', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'تم حفظ مستند المشتريات وتحديث المخازن والقيود بنجاح.'
            ]);

        $this->assertDatabaseHas('purchases', [
            'store_id'     => $store->id,
            'supplier_id'  => $supplier->id,
            'invoice_type' => 'purchase',
            'grand_total'  => 1150.00,
        ]);

        $purchase = Purchase::first();
        $this->assertNotNull($purchase->invoice_number);
        $this->assertNotNull($purchase->journal_entry_id);

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id'  => $purchase->id,
            'item_id'      => $item->id,
            'item_unit_id' => $itemUnit->id,
            'quantity'     => 10.00,
        ]);

        $this->assertDatabaseHas('item_movements', [
            'item_id'       => $item->id,
            'store_id'      => $store->id,
            'movement_type' => 'purchase',
            'document_no'   => $purchase->invoice_number,
            'quantity'      => 10.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id'           => $purchase->journal_entry_id,
            'entry_number' => $purchase->invoice_number,
        ]);
    }

    public function test_can_create_purchase_return_invoice_successfully(): void
    {
        $parentPurchase = Purchase::factory()->create([
            'invoice_type' => 'purchase',
            'user_id'      => $this->user->id,
            'invoice_date' => now()
        ]);
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->create();

        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();

        $payload = [
            'invoice_type'    => 'return',
            'parent_id'       => $parentPurchase->id,
            'store_id'        => $store->id,
            'supplier_id'     => $supplier->id,
            'invoice_date'    => now()->format('Y-m-d H:i:s'),
            'payment_type'    => 'credit',
            'subtotal'        => 500.00,
            'discount_amount' => 0.00,
            'tax_amount'      => 75.00,
            'grand_total'     => 575.00,
            'notes'           => 'مردودات مشتريات آلية مضافة للتجربة بقيم عكسية للكمية والقيود',
            'items' => [
                [
                    'item_id'         => $item->id,
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => 5.00,
                    'unit_cost'       => 100.00,
                    'subtotal'        => 500.00,
                    'discount_amount' => 0.00,
                    'grand_total'     => 500.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/purchases', $payload);

        $response->assertStatus(201);

        $returnInvoice = Purchase::where('invoice_type', 'return')->first();
        $this->assertNotNull($returnInvoice);

        $this->assertDatabaseHas('item_movements', [
            'document_no' => $returnInvoice->invoice_number,
            'quantity'    => -5.00,
        ]);
    }

    public function test_can_show_purchase_invoice_details(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson("/api/purchases/{$purchase->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'             => $purchase->id,
                    'invoice_number' => $purchase->invoice_number,
                ]
            ]);
    }

    public function test_can_update_purchase_invoice_successfully(): void
    {
        // التعديل الإلزامي: إجبار تاريخ الإنشاء التأسيسي ليكون اليوم لتخطي جدار حماية القيود التاريخية
        $purchase = Purchase::factory()->create([
            'user_id'      => $this->user->id,
            'invoice_date' => now()
        ]);
        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();

        $updatePayload = [
            'invoice_type'    => 'purchase',
            'store_id'        => $purchase->store_id,
            'supplier_id'     => $purchase->supplier_id,
            'invoice_date'    => $purchase->invoice_date->format('Y-m-d H:i:s'),
            'payment_type'    => 'credit',
            'subtotal'        => 2000.00,
            'discount_amount' => 0.00,
            'tax_amount'      => 300.00,
            'grand_total'     => 2300.00,
            'notes'           => 'تم تحديث الفاتورة آلياً',
            'items' => [
                [
                    'item_id'         => $item->id,
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => 5.00,
                    'unit_cost'       => 400.00,
                    'subtotal'        => 2000.00,
                    'discount_amount' => 0.00,
                    'grand_total'     => 2000.00,
                ]
            ]
        ];

        $response = $this->putJson("/api/purchases/{$purchase->id}", $updatePayload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('purchases', [
            'id'          => $purchase->id,
            'grand_total' => 2300.00
        ]);
    }

    public function test_can_soft_delete_purchase_invoice_and_reverse_all_effects(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->deleteJson("/api/purchases/{$purchase->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('purchases', [
            'id' => $purchase->id
        ]);

        $this->assertDatabaseMissing('item_movements', [
            'document_no' => $purchase->invoice_number
        ]);
    }

    /**
     * =========================================================================
     * 2. اختبارات التحقق من صحة المدخلات وفشلها (Unhappy Paths - Validation)
     * =========================================================================
     */

    public function test_cannot_create_purchase_return_without_parent_id(): void
    {
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->create();

        $payload = [
            'invoice_type' => 'return',
            'parent_id'    => null,
            'store_id'     => $store->id,
            'supplier_id'  => $supplier->id,
            'invoice_date' => now()->format('Y-m-d H:i:s'),
            'payment_type' => 'credit',
            'subtotal'     => 100.00,
            'grand_total'  => 100.00,
            'items'        => []
        ];

        $response = $this->postJson('/api/purchases', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_cannot_create_purchase_with_zero_or_negative_quantity(): void
    {
        $store = Store::factory()->create();
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();
        $itemUnit = ItemUnit::where('item_id', $item->id)->first();

        $payload = [
            'invoice_type'    => 'purchase',
            'store_id'        => $store->id,
            'supplier_id'     => $supplier->id,
            'invoice_date'    => now()->format('Y-m-d H:i:s'),
            'payment_type'    => 'credit',
            'subtotal'        => 100.00,
            'grand_total'     => 100.00,
            'items' => [
                [
                    'item_id'         => $item->id,
                    'item_unit_id'    => $itemUnit->id,
                    'quantity'        => 0.00,
                    'unit_cost'       => 100.00,
                    'subtotal'        => 100.00,
                    'grand_total'     => 100.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/purchases', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    /**
     * =========================================================================
     * 3. اختبارات حماية الصلاحيات والمنافذ الجانبية (Security & Authorization)
     * =========================================================================
     */

public function test_unauthorized_user_cannot_list_purchases(): void
    {
        $regularUser = User::factory()->create(['email' => 'regular_purchases@test.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/purchases');

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_create_purchase_invoice(): void
    {
        $regularUser = User::factory()->create(['email' => 'regular@test.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->postJson('/api/purchases', [
            'invoice_type' => 'purchase'
        ]);

        $response->assertStatus(403);
    }
}
