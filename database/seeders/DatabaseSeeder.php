<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CycleSetting;
use App\Models\Kid;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Two kids — placeholder names / PINs Claudiu will edit.
        $kids = [
            ['name' => 'Kid One', 'pin' => '1111', 'color' => '#3b82f6'], // blue
            ['name' => 'Kid Two', 'pin' => '2222', 'color' => '#ec4899'], // pink
        ];

        foreach ($kids as $kid) {
            Kid::firstOrCreate(
                ['name' => $kid['name']],
                [
                    'pin' => Hash::make($kid['pin']),
                    'color' => $kid['color'],
                    'dark_mode' => false,
                ]
            );
        }

        // Categories.
        foreach (['Game', 'TV', 'Tablet'] as $name) {
            Category::firstOrCreate(['name' => $name], ['active' => true]);
        }

        // Global cycle settings (single row).
        CycleSetting::current();
    }
}
