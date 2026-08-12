<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_web_order_calculation_and_creation(): void
    {
        $category = Category::create(['name' => 'Alimentation', 'slug' => 'alimentation']);
        $product = Product::create([
            'title' => 'Test Croquettes',
            'barcode' => '9999999999999',
            'category_id' => $category->id,
            'price_buy' => 200,
            'price_sell' => 300,
            'stock_quantity' => 20,
            'min_stock_alert' => 5,
            'is_active' => true,
        ]);

        $payload = [
            'customer_name' => 'Mohamed Karim',
            'phone'         => '0661123456',
            'city'          => 'Marrakech',
            'address'       => 'Guéliz, Av. Mohammed V',
            'shipping_fee'  => 25.00,
            'items'         => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 2,
                ],
            ],
        ];

        // Free shipping if >= 300
        $expectedTotal = ($product->price_sell * 2) + 25.00;

        $response = $this->postJson('/api/v1/shop/orders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Commande enregistrée avec succès !',
            ]);

        $this->assertEquals($expectedTotal, (float) $response->json('data.total_amount'));
        $this->assertEquals('WEB', $response->json('data.source'));
        $this->assertEquals('PENDING', $response->json('data.status'));

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Mohamed Karim',
            'source'        => 'WEB',
            'status'        => 'PENDING',
        ]);
    }

    public function test_public_web_order_with_storefront_field_aliases(): void
    {
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $product = Product::create([
            'title' => 'Collier Chat',
            'barcode' => '8888888888888',
            'category_id' => $category->id,
            'price_buy' => 30,
            'price_sell' => 60,
            'stock_quantity' => 15,
            'min_stock_alert' => 3,
            'is_active' => true,
        ]);

        $payload = [
            'customer_name'    => 'Fatima Zahra',
            'customer_phone'   => '0770987654',
            'customer_city'    => 'Casablanca',
            'customer_address' => 'Maarif, Rue Jura',
            'notes'            => 'Livrer le matin svp',
            'items'            => [
                [
                    'barcode'  => $product->barcode,
                    'quantity' => 1,
                ],
            ],
        ];

        // Free shipping if >= 300, else default 25.00
        $expectedTotal = ($product->price_sell * 1) + 25.00;

        $response = $this->postJson('/api/v1/shop/orders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertEquals($expectedTotal, (float) $response->json('data.total_amount'));
    }

    public function test_public_web_order_click_and_collect_pickup_store(): void
    {
        $store = \App\Models\Store::create([
            'name'      => 'Store Guéliz',
            'code'      => 'STORE_GUELIZ',
            'address'   => 'Av. Mohammed V, Marrakech',
            'phone'     => '0524001122',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Chats', 'slug' => 'chats']);
        $product = Product::create([
            'title'          => 'Litière Silice 5L',
            'barcode'        => '7777777777777',
            'category_id'    => $category->id,
            'price_buy'      => 40,
            'price_sell'     => 75,
            'stock_quantity' => 20,
            'min_stock_alert'=> 2,
            'is_active'      => true,
        ]);

        \Illuminate\Support\Facades\DB::table('store_product_stock')->insert([
            'product_id' => $product->id,
            'store_id'   => $store->id,
            'quantity'   => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'customer_name' => 'Youssef Alami',
            'phone'         => '0612345678',
            'city'          => 'Marrakech',
            'store_id'      => $store->id,
            'delivery_type' => 'PICKUP_STORE',
            'items'         => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/shop/orders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Commande enregistrée avec succès !',
                'data'    => [
                    'customer_name' => 'Youssef Alami',
                    'delivery_type' => 'PICKUP_STORE',
                    'store_id'      => $store->id,
                    'shipping_fee'  => '0.00',
                    'total_amount'  => '150.00',
                    'address'       => 'Retrait en Magasin',
                    'store'         => [
                        'id'   => $store->id,
                        'name' => 'Store Guéliz',
                    ],
                ],
            ]);

        // Assert store-specific stock is decremented: 10 - 2 = 8
        $storeStock = \Illuminate\Support\Facades\DB::table('store_product_stock')
            ->where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->value('quantity');
        $this->assertEquals(8, (float) $storeStock);

        // Assert global stock is decremented: 20 - 2 = 18
        $this->assertEquals(18, $product->fresh()->stock_quantity);

        // Assert order in DB
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Youssef Alami',
            'delivery_type' => 'PICKUP_STORE',
            'store_id'      => $store->id,
            'shipping_fee'  => 0.00,
            'total_amount'  => 150.00,
        ]);
    }

    public function test_admin_dashboard_distinct_pos_and_web_metrics(): void
    {
        Role::create(['name' => 'ADMIN']);
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('ADMIN');

        // POS Order with source = 'POS'
        Order::create([
            'source'        => 'POS',
            'order_type'    => 'POS',
            'status'        => 'COMPLETED',
            'payment_method'=> 'CASH',
            'total_amount'  => 200.00,
        ]);

        // POS Order with source = null
        Order::create([
            'source'        => null,
            'order_type'    => 'POS',
            'status'        => 'COMPLETED',
            'payment_method'=> 'CARD',
            'total_amount'  => 100.00,
        ]);

        // Web Order completed
        Order::create([
            'customer_name' => 'Aicha Web Completed',
            'phone'         => '0611223344',
            'city'          => 'Tanger',
            'address'       => 'Centre Ville',
            'source'        => 'WEB',
            'order_type'    => 'WEB',
            'status'        => 'COMPLETED',
            'payment_method'=> 'COD',
            'shipping_fee'  => 25.00,
            'total_amount'  => 500.00,
        ]);

        // Web Order pending
        Order::create([
            'customer_name' => 'Karim Web Pending',
            'phone'         => '0622334455',
            'city'          => 'Rabat',
            'address'       => 'Agdal',
            'source'        => 'WEB',
            'order_type'    => 'WEB',
            'status'        => 'PENDING',
            'payment_method'=> 'COD',
            'shipping_fee'  => 25.00,
            'total_amount'  => 350.00,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // POS: 200 + 100 = 300 DH (2 orders)
        $this->assertEquals(300.00, (float) $response->json('data.stats.posRevenue'));
        $this->assertEquals(2, (int) $response->json('data.stats.posOrdersCount'));

        // WEB: 500 DH completed (2 orders total: 1 completed, 1 pending)
        $this->assertEquals(500.00, (float) $response->json('data.stats.webRevenue'));
        $this->assertEquals(2, (int) $response->json('data.stats.webOrdersCount'));

        // Total completed revenue: 300 + 500 = 800 DH
        $this->assertEquals(800.00, (float) $response->json('data.stats.totalRevenue'));
        $this->assertEquals(4, (int) $response->json('data.stats.totalOrders'));
    }

    public function test_admin_get_web_orders_and_update_status(): void
    {
        $role = Role::firstOrCreate(['name' => 'ADMIN']);
        $admin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin2@test.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('ADMIN');

        $webOrder = Order::create([
            'customer_name' => 'Salma Web',
            'phone'         => '0677889900',
            'city'          => 'Fes',
            'address'       => 'Atlas',
            'source'        => 'WEB',
            'order_type'    => 'WEB',
            'status'        => 'PENDING',
            'payment_method'=> 'COD',
            'shipping_fee'  => 25.00,
            'total_amount'  => 250.00,
        ]);

        // 1. Test GET /api/v1/admin/web-orders
        $getRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/web-orders');
        $getRes->assertStatus(200)
            ->assertJson(['status' => 'success']);
        
        $this->assertTrue(collect($getRes->json('data'))->contains('customer_name', 'Salma Web'));

        // 2. Test PATCH /api/v1/admin/orders/{id}/status
        $patchRes = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/orders/{$webOrder->id}/status", [
            'status' => 'PROCESSING',
        ]);

        $patchRes->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Statut mis à jour avec succès !',
                'data'    => [
                    'id'     => $webOrder->id,
                    'status' => 'PROCESSING',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id'     => $webOrder->id,
            'status' => 'PROCESSING',
        ]);
    }

    public function test_get_web_orders_filtered_automatically_by_cashier_store(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN']);
        $caissierRole = Role::firstOrCreate(['name' => 'CAISSIER']);

        $storeA = \App\Models\Store::create(['name' => 'Store A (Gueliz)', 'code' => 'STORE_A', 'is_active' => true]);
        $storeB = \App\Models\Store::create(['name' => 'Store B (Agdal)', 'code' => 'STORE_B', 'is_active' => true]);

        $admin = User::create([
            'name'     => 'Admin General',
            'email'    => 'admin_gen@test.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('ADMIN');

        $caissierB = User::create([
            'name'     => 'Caissier Agdal',
            'email'    => 'caisse_agdal@test.com',
            'password' => bcrypt('password'),
            'store_id' => $storeB->id,
        ]);
        $caissierB->assignRole('CAISSIER');

        // Order 1: Store A
        $orderA = Order::create([
            'customer_name' => 'Client Store A',
            'phone'         => '0600000001',
            'city'          => 'Marrakech',
            'address'       => 'Gueliz',
            'source'        => 'WEB',
            'order_type'    => 'WEB',
            'store_id'      => $storeA->id,
            'status'        => 'PENDING',
            'total_amount'  => 100.00,
        ]);

        // Order 2: Store B
        $orderB = Order::create([
            'customer_name' => 'Client Store B',
            'phone'         => '0600000002',
            'city'          => 'Rabat',
            'address'       => 'Agdal',
            'source'        => 'WEB',
            'order_type'    => 'WEB',
            'store_id'      => $storeB->id,
            'status'        => 'PENDING',
            'total_amount'  => 200.00,
        ]);

        // 1. Caissier B should ONLY see Order B
        $resB = $this->actingAs($caissierB, 'sanctum')->getJson('/api/v1/web-orders');
        $resB->assertStatus(200);
        $ordersListB = collect($resB->json('data'));
        $this->assertTrue($ordersListB->contains('customer_name', 'Client Store B'));
        $this->assertFalse($ordersListB->contains('customer_name', 'Client Store A'));

        // 2. Admin should see BOTH orders
        $resAdmin = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/web-orders');
        $resAdmin->assertStatus(200);
        $ordersListAdmin = collect($resAdmin->json('data'));
        $this->assertTrue($ordersListAdmin->contains('customer_name', 'Client Store A'));
        $this->assertTrue($ordersListAdmin->contains('customer_name', 'Client Store B'));
    }
}

