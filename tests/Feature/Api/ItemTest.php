<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;
use App\Models\Item;
use App\Models\Unit;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ItemTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;
    protected Unit $baseUnit;
    protected Unit $subUnit;
    protected PriceList $wholesalePriceList;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد بيئة المصادقة والصلاحيات للمشرف العام
        $this->user = User::factory()->create(['email' => 'admin_items@test.com']);
        Sanctum::actingAs($this->user);

        Gate::before(function ($user, $ability) {
            return $user->email === 'admin_items@test.com' ? true : null;
        });

        // 2. تأسيس البيانات التوجيهية للـ Seeding والاختبار
        $this->category = Category::factory()->create(['name' => 'الأجهزة الإلكترونية']);

        $this->baseUnit = Unit::create([
            'name' => 'حبة',
            'short_name' => 'pcs',
            'is_active' => true
        ]);

        $this->subUnit = Unit::create([
            'name' => 'كرتون',
            'short_name' => 'box',
            'is_active' => true
        ]);

        $this->wholesalePriceList = PriceList::factory()->create([
            'name' => 'قائمة أسعار الجملة',
        ]);
    }

    /**
     * =========================================================================
     * 1. اختبارات المسارات الناجحة (Happy Paths)
     * =========================================================================
     */

    public function test_can_create_complex_item_with_multiple_units_barcodes_and_prices_successfully(): void
    {
        $payload = [
            'category_id'   => $this->category->id,
            'name'          => 'شاشة ذكية 55 بوصة OLED',
            'item_type'     => 'product',
            'profit_margin' => 20.00,
            'base_unit_id'  => $this->baseUnit->id,
            'is_active'     => true,

            'units' => [
                [
                    'unit_id'           => $this->baseUnit->id,
                    'conversion_factor' => 1.0000,
                    'cost'              => 1000.00,
                    'price'             => 1200.00,
                    'barcodes'          => ['BRC-BASE-01', 'BRC-BASE-02'],
                    'prices'            => [
                        [
                            'price_list_id'       => $this->wholesalePriceList->id,
                            'discount_percentage' => 5.00,
                            'price'               => 1140.00
                        ]
                    ]
                ],
                [
                    'unit_id'           => $this->subUnit->id,
                    'conversion_factor' => 10.0000,
                    'cost'              => 9800.00,
                    'price'             => 11500.00,
                    'barcodes'          => ['BRC-SUB-BOX'],
                    'prices'            => [
                        [
                            'price_list_id'       => $this->wholesalePriceList->id,
                            'discount_percentage' => 2.00,
                            'price'               => 11270.00
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/items', $payload);

        // التعديل المعماري: مطابقة الفحص بدقة مع الـ Actual Response الفعلي للمنظومة لديك
        $response->assertStatus(201)
            ->assertJson([
                'status'  => true,
                'message' => 'تم تسجيل الصنف الجديد ومصفوفة أسعاره بنجاح في النظام.'
            ]);

        // أ. التحقق من الحفظ الأساسي في جدول الأصناف
        $this->assertDatabaseHas('items', [
            'name'         => 'شاشة ذكية 55 بوصة OLED',
            'item_type'    => 'product',
            'base_unit_id' => $this->baseUnit->id
        ]);

        $item = Item::where('name', 'شاشة ذكية 55 بوصة OLED')->first();

        // ب. التحقق من بناء سجلات مصفوفة الوحدات (item_units)
        $this->assertDatabaseHas('item_units', [
            'item_id'           => $item->id,
            'unit_id'           => $this->baseUnit->id,
            'conversion_factor' => 1.0000,
            'cost'              => 1000.00,
            'price'             => 1200.00
        ]);

        $this->assertDatabaseHas('item_units', [
            'item_id'           => $item->id,
            'unit_id'           => $this->subUnit->id,
            'conversion_factor' => 10.0000,
            'cost'              => 9800.00,
            'price'             => 11500.00
        ]);

        $baseItemUnit = $item->units()->where('unit_id', $this->baseUnit->id)->first();
        $subItemUnit  = $item->units()->where('unit_id', $this->subUnit->id)->first();

        // ج. التحقق من تفكيك الباركدوات وحفظها في جدولها المطور (item_barcodes)
        $this->assertDatabaseHas('item_barcodes', [
            'item_id'      => $item->id,
            'item_unit_id' => $baseItemUnit->id,
            'barcode'      => 'BRC-BASE-01'
        ]);

        $this->assertDatabaseHas('item_barcodes', [
            'item_id'      => $item->id,
            'item_unit_id' => $baseItemUnit->id,
            'barcode'      => 'BRC-BASE-02'
        ]);

        $this->assertDatabaseHas('item_barcodes', [
            'item_id'      => $item->id,
            'item_unit_id' => $subItemUnit->id,
            'barcode'      => 'BRC-SUB-BOX'
        ]);

        // د. التحقق من سياسات التسعير وحفظها المتزامن (item_unit_prices)
        $this->assertDatabaseHas('item_unit_prices', [
            'item_id'             => $item->id,
            'item_unit_id'        => $baseItemUnit->id,
            'price_list_id'       => $this->wholesalePriceList->id,
            'discount_percentage' => 5.00,
            'price'               => 1140.00
        ]);

        $this->assertDatabaseHas('item_unit_prices', [
            'item_id'             => $item->id,
            'item_unit_id'        => $subItemUnit->id,
            'price_list_id'       => $this->wholesalePriceList->id,
            'discount_percentage' => 2.00,
            'price'               => 11270.00
        ]);
    }

    /**
     * =========================================================================
     * 2. اختبارات الفشل والتحقق من صحة البيانات (Unhappy Paths)
     * =========================================================================
     */

    public function test_cannot_create_item_without_required_fields(): void
    {
        $payload = [
            'name' => '',
            'item_type' => 'invalid_type',
            'base_unit_id' => 999999
        ];

        $response = $this->postJson('/api/items', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'item_type', 'base_unit_id']);
    }

    public function test_cannot_create_item_with_duplicate_barcode_across_system(): void
    {
        // تأسيس صنف قديم يملك باركود محجوز في النظام
        $existingItem = Item::factory()->create();
        $existingUnit = \App\Models\ItemUnit::where('item_id', $existingItem->id)->first();
        \App\Models\ItemBarcode::create([
            'item_id'      => $existingItem->id,
            'item_unit_id' => $existingUnit->id,
            'barcode'      => 'UNIQUE-BARCODE-123'
        ]);

        // محاولة إنشاء صنف جديد بنفس الباركود المحجوز
        $payload = [
            'category_id'   => $this->category->id,
            'name'          => 'صنف مكرر الباركود',
            'item_type'     => 'product',
            'base_unit_id'  => $this->baseUnit->id,
            'units' => [
                [
                    'unit_id'           => $this->baseUnit->id,
                    'conversion_factor' => 1.0000,
                    'cost'              => 50.00,
                    'price'             => 70.00,
                    'barcodes'          => ['UNIQUE-BARCODE-123']
                ]
            ]
        ];

        $response = $this->postJson('/api/items', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['units.0.barcodes.0']);
    }

    /**
     * =========================================================================
     * 3. اختبارات حماية الصلاحيات والمنافذ (Authorization)
     * =========================================================================
     */

    public function test_unauthorized_user_cannot_create_item(): void
    {
        // عزل هوية المشرف العام واستبداله بمستخدم عادي لا يملك الصلاحية
        $regularUser = User::factory()->create(['email' => 'data_clerk@test.com']);
        Sanctum::actingAs($regularUser);

        // التعديل المعماري الحتمي: إرسال Payload سليم تماماً لتخطي طبقة الـ FormRequest Validator بنجاح، لتصطدم الحركة بالـ Policy وترجع الـ 403 المطلوبة
        $payload = [
            'category_id'   => $this->category->id,
            'name'          => 'محاولة اختراق النظام لإدخال صنف',
            'item_type'     => 'product',
            'base_unit_id'  => $this->baseUnit->id,
            'units' => [
                [
                    'unit_id'           => $this->baseUnit->id,
                    'conversion_factor' => 1.0000,
                    'cost'              => 10.00,
                    'price'             => 15.00,
                    'barcodes'          => ['ATTACK-BARCODE']
                ]
            ]
        ];

        $response = $this->postJson('/api/items', $payload);

        $response->assertStatus(403);
    }
}
