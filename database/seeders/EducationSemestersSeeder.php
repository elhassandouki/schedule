<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EducationSemestersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $academicYearId = DB::table('academic_years')->where('is_active', true)->value('id');
        if (!$academicYearId) {
            DB::table('academic_years')->updateOrInsert(['name' => '2026/2027'], ['starts_on'=>'2026-09-01', 'ends_on'=>'2027-06-30', 'is_active'=>true, 'created_at'=>$now, 'updated_at'=>$now]);
            $academicYearId = DB::table('academic_years')->where('name', '2026/2027')->value('id');
        }

        $masters = ['FMSTEIP', 'FMSIPDS'];
        $programs = DB::table('programs')->whereIn('code', [
            'FLDESVT','FLDESLA','FLDEENP','FLDESMA','FLDESLF','FLDESPC',
            'FLDEEPA','TLEENSP','ALDLEES','FMSTEIP','FMSIPDS','FLDESLR',
        ])->get(['id', 'code']);

        foreach ($programs as $program) {
            $lastSemester = in_array($program->code, $masters, true) ? 3 : 6;
            foreach (range(1, $lastSemester) as $number) {
                DB::table('semesters')->updateOrInsert(
                    ['program_id'=>$program->id, 'academic_year_id'=>$academicYearId, 'number'=>$number],
                    ['name'=>'Semestre '.$number, 'created_at'=>$now, 'updated_at'=>$now]
                );
            }
        }
    }
}
