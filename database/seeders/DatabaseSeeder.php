<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Données de référence — règles métier section 2.
        $this->call([
            SocieteSeeder::class,
            FamilleSeeder::class,
            ClientCategorySeeder::class,
            SettingSeeder::class,
            ClassificationKeywordSeeder::class,
            PermissionTemplateSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(StarterAdminSeeder::class);
        }
    }
}
