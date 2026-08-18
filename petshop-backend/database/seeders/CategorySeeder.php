<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // ─── Chien ───────────────────────────────────────────────────────
            [
                'name'        => 'Chien',
                'description' => 'Tout pour votre chien',
                'is_active'   => true,
                'children'    => [
                    ['name' => 'Alimentation Chien',     'description' => 'Croquettes, pâtées et snacks pour chiens'],
                    ['name' => 'Accessoires Chien',      'description' => 'Laisses, colliers, harnais et jouets'],
                    ['name' => 'Hygiène & Soin Chien',   'description' => 'Shampooings, brosses et soins pour chiens'],
                    ['name' => 'Literie Chien',          'description' => 'Paniers, coussins et couchages pour chiens'],
                    ['name' => 'Dressage & Sport',       'description' => 'Produits de dressage et sport canin'],
                ],
            ],
            // ─── Chat ────────────────────────────────────────────────────────
            [
                'name'        => 'Chat',
                'description' => 'Tout pour votre chat',
                'is_active'   => true,
                'children'    => [
                    ['name' => 'Alimentation Chat',      'description' => 'Croquettes, pâtées et friandises pour chats'],
                    ['name' => 'Accessoires Chat',       'description' => 'Jouets, griffoirs et transportins'],
                    ['name' => 'Hygiène & Soin Chat',    'description' => 'Shampooings, anti-parasitaires et soins'],
                    ['name' => 'Literie Chat',           'description' => 'Paniers, couvertures et couchages pour chats'],
                    ['name' => 'Litière',                'description' => 'Litières minérales, végétales et silicone'],
                ],
            ],
            // ─── Oiseau ──────────────────────────────────────────────────────
            [
                'name'        => 'Oiseau',
                'description' => 'Tout pour vos oiseaux',
                'is_active'   => true,
                'children'    => [
                    ['name' => 'Alimentation Oiseau',    'description' => 'Graines, granulés et friandises'],
                    ['name' => 'Cages & Volières',       'description' => 'Cages, volières et perchoirs'],
                    ['name' => 'Accessoires Oiseau',     'description' => 'Jouets et accessoires pour oiseaux'],
                ],
            ],
            // ─── Poisson ─────────────────────────────────────────────────────
            [
                'name'        => 'Poisson & Aquariophilie',
                'description' => 'Aquariums, équipements et alimentation pour poissons',
                'is_active'   => true,
                'children'    => [
                    ['name' => 'Aquariums & Bassins',    'description' => 'Aquariums, bassins et kits complets'],
                    ['name' => 'Alimentation Poisson',   'description' => 'Flocons, granulés et aliments vivants'],
                    ['name' => 'Filtration & Pompes',    'description' => 'Filtres, pompes et aération'],
                    ['name' => 'Décoration Aquarium',    'description' => 'Plantes, roches et substrats'],
                ],
            ],
            // ─── Rongeur ─────────────────────────────────────────────────────
            [
                'name'        => 'Rongeur & Lapin',
                'description' => 'Tout pour rongeurs et lapins',
                'is_active'   => true,
                'children'    => [
                    ['name' => 'Alimentation Rongeur',   'description' => 'Granulés, foin et friandises'],
                    ['name' => 'Cages Rongeur',          'description' => 'Habitats pour rongeurs et lapins'],
                    ['name' => 'Accessoires Rongeur',    'description' => 'Roues, tunnels et litières'],
                ],
            ],
            // ─── Reptile ─────────────────────────────────────────────────────
            [
                'name'        => 'Reptile & Amphibien',
                'description' => 'Terrariums et soins pour reptiles',
                'is_active'   => true,
                'children'    => [
                    ['name' => 'Alimentation Reptile',      'description' => 'Insectes, proies et compléments'],
                    ['name' => 'Terrariums & Équipements',  'description' => 'Terrariums, lampes et chauffage'],
                ],
            ],
            // ─── Santé ───────────────────────────────────────────────────────
            [
                'name'        => 'Santé & Pharmacie',
                'description' => 'Anti-parasitaires, compléments et produits vétérinaires',
                'is_active'   => true,
                'children'    => [
                    ['name' => 'Anti-parasitaires',         'description' => 'Produits anti-puces, tiques et vers'],
                    ['name' => 'Compléments Alimentaires',  'description' => 'Vitamines et compléments santé'],
                    ['name' => 'Premiers Secours',          'description' => 'Pansements, désinfectants et trousses'],
                ],
            ],
        ];

        foreach ($categories as $data) {
            $children = $data['children'] ?? [];
            unset($data['children']);

            $data['slug'] = Str::slug($data['name']);

            /** @var Category $parent */
            $parent = Category::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            foreach ($children as $child) {
                $child['slug']      = Str::slug($child['name']);
                $child['parent_id'] = $parent->id;
                $child['is_active'] = true;

                Category::firstOrCreate(
                    ['slug' => $child['slug']],
                    $child
                );
            }
        }

        $this->command->info('✅ Categories seeded successfully.');
    }
}
