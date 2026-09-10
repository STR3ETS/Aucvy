<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        /*
         | De categorieën zijn geen testdata maar referentiedata: de funnel en
         | de filters kunnen er niet zonder. Deze seeder hoort dus ook op
         | productie te draaien, en is daarom idempotent (updateOrCreate).
         */
        $this->call(CategorySeeder::class);
    }
}
