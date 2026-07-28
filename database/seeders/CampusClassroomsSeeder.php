<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CampusClassroomsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        foreach (range(1, 20) as $number) {
            $this->room('Bloc A - Salle '.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 70, $now);
        }
        foreach (range(1, 5) as $number) {
            $this->room('Bloc B - Salle '.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 107, $now);
        }
        $this->room('Amphi 1', 210, $now);
    }

    private function room(string $name, int $capacity, $now): void
    {
        DB::table('classrooms')->updateOrInsert(['name' => $name], [
            'capacity' => $capacity, 'type' => 'cours', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
