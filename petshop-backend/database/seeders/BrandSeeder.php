<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            // ── Nutrition Chien / Chat ─────────────────────────────────────────
            ['name' => 'Royal Canin',       'is_active' => true],
            ['name' => 'Hill\'s',           'is_active' => true],
            ['name' => 'Purina Pro Plan',   'is_active' => true],
            ['name' => 'Eukanuba',          'is_active' => true],
            ['name' => 'Advance',           'is_active' => true],
            ['name' => 'Acana',             'is_active' => true],
            ['name' => 'Orijen',            'is_active' => true],
            ['name' => 'Brit Care',         'is_active' => true],
            ['name' => 'Josera',            'is_active' => true],
            ['name' => 'Pedigree',          'is_active' => true],
            ['name' => 'Whiskas',           'is_active' => true],
            ['name' => 'Felix',             'is_active' => true],
            ['name' => 'Friskies',          'is_active' => true],
            ['name' => 'Iams',              'is_active' => true],
            ['name' => 'Vitakraft',         'is_active' => true],
            // ── Hygiène & Soin ────────────────────────────────────────────────
            ['name' => 'Frontline',         'is_active' => true],
            ['name' => 'Advantix',          'is_active' => true],
            ['name' => 'Beaphar',           'is_active' => true],
            ['name' => 'Trixie',            'is_active' => true],
            ['name' => 'Ferplast',          'is_active' => true],
            ['name' => 'Nobby',             'is_active' => true],
            // ── Aquariophilie ─────────────────────────────────────────────────
            ['name' => 'Tetra',             'is_active' => true],
            ['name' => 'Sera',              'is_active' => true],
            ['name' => 'JBL',               'is_active' => true],
            ['name' => 'Fluval',            'is_active' => true],
            // ── Rongeurs / Reptiles ───────────────────────────────────────────
            ['name' => 'Versele-Laga',      'is_active' => true],
            ['name' => 'Padovan',           'is_active' => true],
            ['name' => 'Repti Zoo',         'is_active' => true],
            // ── Marque Locale ─────────────────────────────────────────────────
            ['name' => 'PetShop Maroc',     'is_active' => true],
        ];

        foreach ($brands as $data) {
            $data['slug'] = Str::slug($data['name']);
            Brand::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        $this->command->info('✅ Brands seeded successfully (' . count($brands) . ' marques).');
    }
}
