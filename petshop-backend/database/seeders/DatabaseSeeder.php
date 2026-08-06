<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Roles
        $adminRole = Role::create(['name' => 'ADMIN']);
        $caissierRole = Role::create(['name' => 'CAISSIER']);
        $magasinierRole = Role::create(['name' => 'MAGASINIER']);

        // 2. Create Default Admin
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@petshop.ma',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole($adminRole);

        // 3. Create Default Caissier
        $caissier = User::create([
            'name' => 'Caissier 1',
            'email' => 'caisse@petshop.ma',
            'password' => Hash::make('password123'),
        ]);
        $caissier->assignRole($caissierRole);

        // 4. Create Default Magasinier
        $magasinier = User::create([
            'name' => 'Magasinier 1',
            'email' => 'stock@petshop.ma',
            'password' => Hash::make('password123'),
        ]);
        $magasinier->assignRole($magasinierRole);

        // 5. Create Sample Category & Product for Barcode Testing
        $category = Category::create([
            'name' => 'Alimentation Chien',
            'slug' => 'alimentation-chien',
            'description' => 'Croquettes et nourriture pour chiens',
        ]);

        Product::create([
            'category_id' => $category->id,
            'barcode' => '6111234567890',
            'title' => 'Croquettes Royal Canin 10kg',
            'description' => 'Croquettes premium pour chien adulte',
            'price_buy' => 350.00,
            'price_sell' => 450.00,
            'stock_quantity' => 20,
            'min_stock_alert' => 5,
            'is_active' => true,
        ]);
    }
}
