<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Analog',       'slug' => 'analog',      'sort_order' => 1],
            ['name' => 'Digital',      'slug' => 'digital',     'sort_order' => 2],
            ['name' => 'Premium',      'slug' => 'premium',     'sort_order' => 3],
            ['name' => 'Animated',     'slug' => 'animated',    'sort_order' => 4],
            ['name' => 'Classic',      'slug' => 'classic',     'sort_order' => 5],
            ['name' => 'Sport',        'slug' => 'sport',       'sort_order' => 6],
            ['name' => 'AOD Friendly', 'slug' => 'aod-friendly','sort_order' => 7],
            ['name' => 'Free',         'slug' => 'free',        'sort_order' => 8],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}

