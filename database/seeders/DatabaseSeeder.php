<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * New architecture: Subjects (independent) + Student Groups (semester-based)
     */
    public function run(): void
    {
        $this->call(StudentGroupsArchitectureSeeder::class);
    }
}
