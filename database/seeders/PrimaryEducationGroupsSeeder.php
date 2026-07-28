<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrimaryEducationGroupsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $academicYearId = DB::table('academic_years')->where('is_active', true)->value('id');
        if (!$academicYearId) return;

        $semesters = DB::table('semesters as s')
            ->join('programs as p', 'p.id', '=', 's.program_id')
            ->where('s.academic_year_id', $academicYearId)
            ->whereIn('p.code', ['FLDEENP', 'FLDEEPA', 'TLEENSP'])
            ->whereIn('s.number', [1, 3, 5])
            ->select('s.id', 'p.id as program_id')
            ->get();

        foreach ($semesters as $semester) {
            foreach (range(1, 7) as $number) {
                DB::table('student_groups')->updateOrInsert(
                    ['semester_id' => $semester->id, 'name' => 'G'.$number],
                    ['program_id' => $semester->program_id, 'student_count' => 0, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }
}
