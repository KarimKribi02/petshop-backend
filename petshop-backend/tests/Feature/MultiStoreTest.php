<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Store;
use App\Models\StockMovement;
use App\Models\Order;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class MultiStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_with_assigned_store(): void
    {
        $adminRole = Role::create(['name' => 'ADMIN']);
        $caissierRole = Role::create(['name' => 'CAISSIER']);

        $admin = User::create([
            'name'     => 'Admin Test',
            'email'    => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole($adminRole);

        $storeA = Store::create([
            'name'      => 'Store A (Gueliz)',
            'code'      => 'STORE_A',
            'address'   => 'Gueliz, Marrakech',
            'phone'     => '0524000000',
            'is_active' => true,
        ]);

        $payload = [
            'name'     => 'Caissier Gueliz',
            'email'    => 'caissier.gueliz@test.com',
            'password' => 'password123',
            'role'     => 'CAISSIER',
            'store_id' => $storeA->id,
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/users', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Utilisateur créé avec succès!',
                'data'    => [
                    'name'     => 'Caissier Gueliz',
                    'email'    => 'caissier.gueliz@test.com',
                    'store_id' => $storeA->id,
                    'store'    => [
                        'id'   => $storeA->id,
                        'name' => 'Store A (Gueliz)',
                        'code' => 'STORE_A',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email'    => 'caissier.gueliz@test.com',
            'store_id' => $storeA->id,
        ]);
    }

    public function test_stock_entry_updates_global_and_store_specific_stock(): void
    {
        $magasinierRole = Role::create(['name' => 'MAGASINIER']);

        $storeB = Store::create([
            'name'      => 'Store B (Agdal)',
            'code'      => 'STORE_B',
            'address'   => 'Agdal, Rabat',
            'phone'     => '0537000000',
            'is_active' => true,
        ]);

        $magasinier = User::create([
            'name'     => 'Magasinier Agdal',
            'email'    => 'magasinier.agdal@test.com',
            'password' => bcrypt('password'),
            'store_id' => $storeB->id,
        ]);
        $magasinier->assignRole($magasinierRole);

        $category = Category::create(['name' => 'Alimentation', 'slug' => 'alimentation']);
        $product = Product::create([
            'title'          => 'Croquettes Royal Canin',
            'barcode'        => '611100000001',
            'category_id'    => $category->id,
            'price_buy'      => 200,
            'price_sell'     => 300,
            'stock_quantity' => 10,
            'min_stock_alert'=> 5,
            'is_active'      => true,
        ]);

        $payload = [
            'barcode'   => $product->barcode,
            'quantity'  => 15,
            'bl_number' => 'BL-2026-001',
            'notes'     => 'Arrivage Agdal',
        ];

        $response = $this->actingAs($magasinier, 'sanctum')->postJson('/api/v1/stock/scan-in', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);

        // Global product stock incremented from 10 to 25
        $product->refresh();
        $this->assertEquals(25, $product->stock_quantity);

        // Store specific stock in store_product_stock table = 15
        $storeStock = DB::table('store_product_stock')
            ->where('product_id', $product->id)
            ->where('store_id', $storeB->id)
            ->value('quantity');

        $this->assertEquals(15, (float) $storeStock);

        // Stock movement recorded with store_id
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id'    => $magasinier->id,
            'store_id'   => $storeB->id,
            'quantity'   => 15,
            'bl_number'  => 'BL-2026-001',
        ]);
    }

    public function test_pos_checkout_deducts_global_and_store_specific_stock(): void
    {
        $caissierRole = Role::create(['name' => 'CAISSIER']);

        $storeA = Store::create([
            'name'      => 'Store A (Gueliz)',
            'code'      => 'STORE_A',
            'address'   => 'Gueliz, Marrakech',
            'is_active' => true,
        ]);

        $caissier = User::create([
            'name'     => 'Caissier Gueliz',
            'email'    => 'caissier.g@test.com',
            'password' => bcrypt('password'),
            'store_id' => $storeA->id,
        ]);
        $caissier->assignRole($caissierRole);

        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $product = Product::create([
            'title'          => 'Laisse Premium',
            'barcode'        => '611100000002',
            'category_id'    => $category->id,
            'price_buy'      => 50,
            'price_sell'     => 100,
            'stock_quantity' => 20,
            'min_stock_alert'=> 2,
            'is_active'      => true,
        ]);

        // Initialize Store A stock with 20
        DB::table('store_product_stock')->insert([
            'product_id' => $product->id,
            'store_id'   => $storeA->id,
            'quantity'   => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'payment_method' => 'CASH',
            'items'          => [
                [
                    'barcode'  => $product->barcode,
                    'quantity' => 3,
                ],
            ],
        ];

        $response = $this->actingAs($caissier, 'sanctum')->postJson('/api/v1/pos/checkout', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Vente effectuée avec succès!',
            ]);

        // Global stock reduced: 20 - 3 = 17
        $product->refresh();
        $this->assertEquals(17, $product->stock_quantity);

        // Store specific stock reduced: 20 - 3 = 17
        $storeStock = DB::table('store_product_stock')
            ->where('product_id', $product->id)
            ->where('store_id', $storeA->id)
            ->value('quantity');

        $this->assertEquals(17, (float) $storeStock);

        // Order recorded with store_id
        $this->assertDatabaseHas('orders', [
            'user_id'      => $caissier->id,
            'store_id'     => $storeA->id,
            'source'       => 'POS',
            'total_amount' => 300.00,
            'status'       => 'COMPLETED',
        ]);
    }

    public function test_stores_crud_management(): void
    {
        $adminRole = Role::create(['name' => 'ADMIN']);
        $admin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin_stores@test.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole($adminRole);

        // 1. Create Store
        $createRes = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/stores', [
            'name'    => 'Magasin Casablanca Anfa',
            'code'    => 'STORE_ANFA',
            'address' => 'Boulevard d Anfa, Casablanca',
            'phone'   => '0522000000',
        ]);

        $createRes->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Magasin créé avec succès!',
                'data'    => [
                    'name' => 'Magasin Casablanca Anfa',
                    'code' => 'STORE_ANFA',
                ],
            ]);

        $storeId = $createRes->json('data.id');

        // 2. List Stores
        $listRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/stores');
        $listRes->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertTrue(collect($listRes->json('data'))->contains('code', 'STORE_ANFA'));

        // 3. Update Store
        $updateRes = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/stores/{$storeId}", [
            'name'    => 'Magasin Casablanca Anfa (Updated)',
            'code'    => 'STORE_ANFA',
            'address' => 'Bd d Anfa & Corniche',
        ]);

        $updateRes->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Magasin mis à jour avec succès!',
                'data'    => [
                    'name' => 'Magasin Casablanca Anfa (Updated)',
                ],
            ]);
    }

    public function test_pos_products_endpoint_returns_store_specific_stock(): void
    {
        $caissierRole = Role::create(['name' => 'CAISSIER']);

        $storeA = Store::create(['name' => 'Store A (Gueliz)', 'code' => 'STORE_A', 'is_active' => true]);
        $storeB = Store::create(['name' => 'Store B (Agdal)', 'code' => 'STORE_B', 'is_active' => true]);

        $caissierA = User::create([
            'name'     => 'Caissier A',
            'email'    => 'caisseA@test.com',
            'password' => bcrypt('password'),
            'store_id' => $storeA->id,
        ]);
        $caissierA->assignRole($caissierRole);

        $category = Category::create(['name' => 'Alimentation', 'slug' => 'alimentation']);
        $product = Product::create([
            'title'          => 'Croquettes Chat 2kg',
            'barcode'        => '611100000099',
            'category_id'    => $category->id,
            'price_buy'      => 40,
            'price_sell'     => 80,
            'stock_quantity' => 100, // Total global
            'is_active'      => true,
        ]);

        // Store A has 35 units
        DB::table('store_product_stock')->insert([
            'product_id' => $product->id,
            'store_id'   => $storeA->id,
            'quantity'   => 35,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Store B has 65 units
        DB::table('store_product_stock')->insert([
            'product_id' => $product->id,
            'store_id'   => $storeB->id,
            'quantity'   => 65,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // When Caissier A requests /api/v1/pos/products, stock should be 35
        $resA = $this->actingAs($caissierA, 'sanctum')->getJson('/api/v1/pos/products');
        $resA->assertStatus(200);

        $productDataA = collect($resA->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($productDataA);
        $this->assertEquals(35, (float) $productDataA['quantity']);
        $this->assertEquals(35, (float) $productDataA['stock_quantity']);
    }

    public function test_public_shop_products_returns_store_stocks_breakdown(): void
    {
        $storeA = Store::create(['name' => 'Store A (Gueliz)', 'code' => 'STORE_A', 'is_active' => true]);
        $storeB = Store::create(['name' => 'Store B (Agdal)', 'code' => 'STORE_B', 'is_active' => true]);

        $category = Category::create(['name' => 'Chats', 'slug' => 'chats']);
        $product = Product::create([
            'title'          => 'Litière Silice Premium',
            'barcode'        => '622200000011',
            'category_id'    => $category->id,
            'price_buy'      => 50,
            'price_sell'     => 90,
            'stock_quantity' => 50,
            'is_active'      => true,
        ]);

        DB::table('store_product_stock')->insert([
            'product_id' => $product->id,
            'store_id'   => $storeA->id,
            'quantity'   => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_product_stock')->insert([
            'product_id' => $product->id,
            'store_id'   => $storeB->id,
            'quantity'   => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Test GET /api/v1/shop-products
        $res = $this->getJson('/api/v1/shop-products');
        $res->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $productData = collect($res->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($productData);
        $this->assertEquals(50, (float) $productData['total_stock']);
        $this->assertArrayHasKey('stores_stock', $productData);
        $this->assertCount(2, $productData['stores_stock']);

        $storeAStock = collect($productData['stores_stock'])->firstWhere('store_id', $storeA->id);
        $this->assertEquals(20, (float) $storeAStock['quantity']);
        $this->assertEquals('Store A (Gueliz)', $storeAStock['store_name']);

        $storeBStock = collect($productData['stores_stock'])->firstWhere('store_id', $storeB->id);
        $this->assertEquals(30, (float) $storeBStock['quantity']);
        $this->assertEquals('Store B (Agdal)', $storeBStock['store_name']);

        // 2. Test GET /api/v1/shop/products
        $resPublic = $this->getJson('/api/v1/shop/products');
        $resPublic->assertStatus(200);
        $publicProduct = collect($resPublic->json('data.data'))->firstWhere('id', $product->id);
        $this->assertNotNull($publicProduct);
        $this->assertArrayHasKey('stores_stock', $publicProduct);
    }
}

