<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Helper closures to resolve IDs by slug
        $cat   = fn(string $slug) => Category::where('slug', Str::slug($slug))->value('id');
        $brand = fn(string $slug) => Brand::where('slug',    Str::slug($slug))->value('id');

        $products = [

            // ════════════════════════════════════════════════════════════
            //  CHIEN – Alimentation
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Alimentation Chien'),
                'brand_id'        => $brand('Royal Canin'),
                'barcode'         => '3182550402323',
                'title'           => 'Royal Canin Medium Adult 15kg',
                'description'     => 'Croquettes pour chien de taille moyenne (11–25 kg), formule équilibrée.',
                'price_buy'       => 420.00,
                'price_sell'      => 550.00,
                'stock_quantity'  => 30,
                'min_stock_alert' => 5,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Alimentation Chien'),
                'brand_id'        => $brand('Purina Pro Plan'),
                'barcode'         => '7613035154124',
                'title'           => 'Pro Plan Adult Large Robust 14kg',
                'description'     => 'Croquettes riches en poulet pour grandes races robustes.',
                'price_buy'       => 390.00,
                'price_sell'      => 520.00,
                'stock_quantity'  => 20,
                'min_stock_alert' => 4,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Alimentation Chien'),
                'brand_id'        => $brand('Pedigree'),
                'barcode'         => '5900951248771',
                'title'           => 'Pedigree Adult Bœuf & Légumes 3kg',
                'description'     => 'Croquettes économiques au bœuf et légumes pour chiens adultes.',
                'price_buy'       => 85.00,
                'price_sell'      => 120.00,
                'stock_quantity'  => 50,
                'min_stock_alert' => 10,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  CHIEN – Accessoires
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Accessoires Chien'),
                'brand_id'        => $brand('Trixie'),
                'barcode'         => '4011905192550',
                'title'           => 'Laisse Trixie Premium 120cm Rouge',
                'description'     => 'Laisse robuste en nylon, réglable, pour chiens jusqu\'à 40 kg.',
                'price_buy'       => 35.00,
                'price_sell'      => 65.00,
                'stock_quantity'  => 40,
                'min_stock_alert' => 8,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Accessoires Chien'),
                'brand_id'        => $brand('Ferplast'),
                'barcode'         => '8010690085931',
                'title'           => 'Harnais Ferplast Kasko M',
                'description'     => 'Harnais ergonomique taille M pour chiens de 8 à 15 kg.',
                'price_buy'       => 90.00,
                'price_sell'      => 149.00,
                'stock_quantity'  => 25,
                'min_stock_alert' => 5,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  CHIEN – Hygiène & Soin
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Hygiène & Soin Chien'),
                'brand_id'        => $brand('Frontline'),
                'barcode'         => '3661103043552',
                'title'           => 'Frontline Combo Chien L (3 pipettes)',
                'description'     => 'Anti-puces et anti-tiques pour chiens de 20 à 40 kg.',
                'price_buy'       => 110.00,
                'price_sell'      => 165.00,
                'stock_quantity'  => 35,
                'min_stock_alert' => 7,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Hygiène & Soin Chien'),
                'brand_id'        => $brand('Beaphar'),
                'barcode'         => '8711231132683',
                'title'           => 'Shampooing Beaphar Chien 250ml',
                'description'     => 'Shampooing doux sans parabènes, parfum pomme verte.',
                'price_buy'       => 28.00,
                'price_sell'      => 55.00,
                'stock_quantity'  => 30,
                'min_stock_alert' => 6,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  CHAT – Alimentation
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Alimentation Chat'),
                'brand_id'        => $brand('Royal Canin'),
                'barcode'         => '3182550705899',
                'title'           => 'Royal Canin Indoor Cat 2kg',
                'description'     => 'Croquettes pour chats vivant en intérieur.',
                'price_buy'       => 130.00,
                'price_sell'      => 185.00,
                'stock_quantity'  => 40,
                'min_stock_alert' => 8,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Alimentation Chat'),
                'brand_id'        => $brand('Whiskas'),
                'barcode'         => '5900951232756',
                'title'           => 'Whiskas Adulte Poulet 2kg',
                'description'     => 'Croquettes au poulet pour chats adultes.',
                'price_buy'       => 60.00,
                'price_sell'      => 95.00,
                'stock_quantity'  => 60,
                'min_stock_alert' => 10,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Alimentation Chat'),
                'brand_id'        => $brand('Felix'),
                'barcode'         => '5900951159573',
                'title'           => 'Felix en gelée Variétés 12×85g',
                'description'     => 'Pâtées en gelée variétés saumon, bœuf, volaille.',
                'price_buy'       => 42.00,
                'price_sell'      => 72.00,
                'stock_quantity'  => 55,
                'min_stock_alert' => 10,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  CHAT – Litière
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Litière'),
                'brand_id'        => $brand('Beaphar'),
                'barcode'         => '8711231160076',
                'title'           => 'Litière Beaphar Agglomérante 10kg',
                'description'     => 'Litière minérale agglomérante, neutralise les odeurs.',
                'price_buy'       => 55.00,
                'price_sell'      => 90.00,
                'stock_quantity'  => 45,
                'min_stock_alert' => 8,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  CHAT – Hygiène & Soin
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Hygiène & Soin Chat'),
                'brand_id'        => $brand('Frontline'),
                'barcode'         => '3661103043484',
                'title'           => 'Frontline Combo Chat (3 pipettes)',
                'description'     => 'Anti-puces et anti-tiques pour chats et lapins.',
                'price_buy'       => 100.00,
                'price_sell'      => 155.00,
                'stock_quantity'  => 30,
                'min_stock_alert' => 6,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  OISEAU – Alimentation
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Alimentation Oiseau'),
                'brand_id'        => $brand('Versele-Laga'),
                'barcode'         => '5410340218311',
                'title'           => 'Versele-Laga Prestige Perruches 2kg',
                'description'     => 'Mélange de graines premium pour perruches.',
                'price_buy'       => 38.00,
                'price_sell'      => 65.00,
                'stock_quantity'  => 35,
                'min_stock_alert' => 7,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Alimentation Oiseau'),
                'brand_id'        => $brand('Vitakraft'),
                'barcode'         => '4008239181503',
                'title'           => 'Vitakraft Menu Canaris 1kg',
                'description'     => 'Mélange de graines enrichi en vitamines pour canaris.',
                'price_buy'       => 30.00,
                'price_sell'      => 52.00,
                'stock_quantity'  => 28,
                'min_stock_alert' => 5,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  POISSON – Alimentation
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Alimentation Poisson'),
                'brand_id'        => $brand('Tetra'),
                'barcode'         => '4004218771581',
                'title'           => 'Tetra Min Flakes 250ml',
                'description'     => 'Nourriture en flocons complète pour poissons tropicaux.',
                'price_buy'       => 28.00,
                'price_sell'      => 50.00,
                'stock_quantity'  => 40,
                'min_stock_alert' => 8,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Alimentation Poisson'),
                'brand_id'        => $brand('Sera'),
                'barcode'         => '4001942011167',
                'title'           => 'Sera Goldfish Nature 1000ml',
                'description'     => 'Nourriture pour poissons rouges sans colorants artificiels.',
                'price_buy'       => 35.00,
                'price_sell'      => 60.00,
                'stock_quantity'  => 25,
                'min_stock_alert' => 5,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  POISSON – Filtration
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Filtration & Pompes'),
                'brand_id'        => $brand('JBL'),
                'barcode'         => '4014162600011',
                'title'           => 'JBL CristalProfi e702 greenline',
                'description'     => 'Filtre externe silencieux pour aquariums de 60 à 200 L.',
                'price_buy'       => 380.00,
                'price_sell'      => 580.00,
                'stock_quantity'  => 10,
                'min_stock_alert' => 2,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  RONGEUR – Alimentation
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Alimentation Rongeur'),
                'brand_id'        => $brand('Versele-Laga'),
                'barcode'         => '5410340625156',
                'title'           => 'Versele-Laga Complete Hamster 800g',
                'description'     => 'Alimentation complète en granulés pour hamsters.',
                'price_buy'       => 30.00,
                'price_sell'      => 55.00,
                'stock_quantity'  => 30,
                'min_stock_alert' => 6,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Alimentation Rongeur'),
                'brand_id'        => $brand('Padovan'),
                'barcode'         => '8009470003834',
                'title'           => 'Padovan Foin Grand Prix 500g',
                'description'     => 'Foin naturel de prairie pour lapins et cobayes.',
                'price_buy'       => 22.00,
                'price_sell'      => 40.00,
                'stock_quantity'  => 50,
                'min_stock_alert' => 10,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  REPTILE – Alimentation
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Alimentation Reptile'),
                'brand_id'        => $brand('Repti Zoo'),
                'barcode'         => '6971490809012',
                'title'           => 'Repti Zoo Insect Blend 60g',
                'description'     => 'Mélange d\'insectes séchés pour reptiles insectivores.',
                'price_buy'       => 45.00,
                'price_sell'      => 80.00,
                'stock_quantity'  => 20,
                'min_stock_alert' => 4,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],

            // ════════════════════════════════════════════════════════════
            //  SANTÉ & PHARMACIE
            // ════════════════════════════════════════════════════════════
            [
                'category_id'     => $cat('Anti-parasitaires'),
                'brand_id'        => $brand('Advantix'),
                'barcode'         => '4007221036456',
                'title'           => 'Advantix Chien M 4–10 kg (3 pipettes)',
                'description'     => 'Répulsif et insecticide pour chiens de 4 à 10 kg.',
                'price_buy'       => 95.00,
                'price_sell'      => 150.00,
                'stock_quantity'  => 25,
                'min_stock_alert' => 5,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
            [
                'category_id'     => $cat('Compléments Alimentaires'),
                'brand_id'        => $brand('Beaphar'),
                'barcode'         => '8711231138586',
                'title'           => 'Beaphar Oméga 3 & 6 Chien 150ml',
                'description'     => 'Huile de saumon et tournesol pour un pelage brillant.',
                'price_buy'       => 55.00,
                'price_sell'      => 90.00,
                'stock_quantity'  => 20,
                'min_stock_alert' => 4,
                'unit_type'       => 'PIECE',
                'is_active'       => true,
            ],
        ];

        $created = 0;
        foreach ($products as $data) {
            Product::firstOrCreate(
                ['barcode' => $data['barcode']],
                $data
            );
            $created++;
        }

        $this->command->info("✅ Products seeded successfully ({$created} produits).");
    }
}
