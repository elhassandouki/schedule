<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EducationProgramsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        DB::table('departments')->updateOrInsert(['code' => 'EDUC'], ['name' => 'Éducation', 'created_at' => $now, 'updated_at' => $now]);
        $departmentId = DB::table('departments')->where('code', 'EDUC')->value('id');

        $programs = [
            ['FLDESVT', 'Licence d’Éducation – Enseignement Secondaire – Sciences de la Vie et de la Terre', 'من سلك الإجازة في التربية – تخصص التعليم الثانوي – علوم الحياة والأرض'],
            ['FLDESLA', 'Licence d’Éducation – Enseignement Secondaire – Langue Anglaise', 'من سلك الإجازة في التربية – تخصص التعليم الثانوي – اللغة الإنجليزية'],
            ['FLDEENP', 'Licence d’Éducation – Enseignement Primaire', 'من سلك الإجازة في التربية – تخصص التعليم الابتدائي'],
            ['FLDESMA', 'Licence d’Éducation – Enseignement Secondaire – Mathématiques', 'من سلك الإجازة في التربية – تخصص التعليم الثانوي – الرياضيات'],
            ['FLDESLF', 'Licence d’Éducation – Enseignement Secondaire – Langue Française', 'من سلك الإجازة في التربية – تخصص التعليم الثانوي – اللغة الفرنسية'],
            ['FLDESPC', 'Licence d’Éducation – Enseignement Secondaire – Physique Chimie', 'من سلك الإجازة في التربية – تخصص التعليم الثانوي – الفيزياء والكيمياء'],
            ['FLDEEPA', 'Licence d’Éducation – Enseignement Primaire – Option Amazigh', 'من سلك الإجازة في التربية – تخصص التعليم الابتدائي – الأمازيغية'],
            ['TLEENSP', 'Licence d’Éducation – Enseignement Primaire', 'من سلك الإجازة في التربية – تخصص التعليم الابتدائي'],
            ['ALDLEES', 'Licence d’Éducation – Enseignement Secondaire – Langue Anglaise', 'من سلك الإجازة في التربية – تخصص التعليم الثانوي – اللغة الإنجليزية'],
            ['FMSTEIP', 'Master Technologies éducatives et Innovation Pédagogique', 'من سلك ماستر التكنولوجيا التربوية والابتكار البيداغوجي'],
            ['FMSIPDS', 'Master Ingénierie Pédagogique et Didactique des Sciences', 'من سلك ماستر الهندسة البيداغوجية و ديداكتيك العلوم'],
            ['FLDESLR', 'Licence d’Éducation – Enseignement Secondaire – Langue Arabe', 'من سلك الإجازة في التربية – تخصص التعليم الثانوي – اللغة العربية'],
        ];

        foreach ($programs as [$code, $name, $nameAr]) {
            DB::table('programs')->updateOrInsert(['code' => $code], ['department_id' => $departmentId, 'name' => $name, 'name_ar' => $nameAr, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
