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
         = Role::firstOrCreate(['name' => 'ADMIN']);
         = Role::firstOrCreate(['name' => 'CAISSIER']);
         = Role::firstOrCreate(['name' => 'MAGASINIER']);

        // 2. Create Default Admin
         = User::firstOrCreate(
            ['email' => 'admin@petshop.ma'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
            ]
        );
        ->assignRole();

        // 3. Create Default Caissier
         = User::firstOrCreate(
            ['email' => 'caisse@petshop.ma'],
            [
                'name' => 'Caissier 1',
                'password' => Hash::make('password123'),
            ]
        );
        ->assignRole();

        // 4. Create Default Magasinier
         = User::firstOrCreate(
            ['email' => 'stock@petshop.ma'],
            [
                'name' => 'Magasinier 1',
                'password' => Hash::make('password123'),
            ]
        );
        ->assignRole();

        // 5. Create Sample Category & Product for Barcode Testing
         = Category::firstOrCreate(
            ['slug' => 'alimentation-chien'],
            [
                'name' => 'Alimentation Chien',
                'description' => 'Croquettes et nourriture pour chiens',
            ]
        );

        Product::firstOrCreate(
            ['barcode' => '6111234567890'],
            [
                'category_id' => ->id,
                'title' => 'Croquettes Royal Canin 10kg',
                'description' => 'Croquettes premium pour chien adulte',
                'price_buy' => 350.00,
                'price_sell' => 450.00,
                'stock_quantity' => 20,
                'min_stock_alert' => 5,
                'is_active' => true,
            ]
        );

        // 6. Seed FAQs
        ->call(FaqSeeder::class);
    }
}
