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
     */
    public function run(): void
    {
        $this->call(EducationProgramsSeeder::class);
        $this->call(EducationSemestersSeeder::class);
        $this->call(PrimaryEducationGroupsSeeder::class);
        $this->call(CampusClassroomsSeeder::class);
        $this->call(ManualTimetableDemoSeeder::class);
        User::firstOrCreate(['email' => 'admin@planif-uni.test'], [
            'name' => 'Super Administrateur',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
        ]);

        $now = now();
        DB::table('academic_years')->updateOrInsert(['name'=>'2026/2027'], ['starts_on'=>'2026-09-01','ends_on'=>'2027-07-15','is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
        DB::table('departments')->updateOrInsert(['code'=>'INFO'], ['name'=>'Informatique','created_at'=>$now,'updated_at'=>$now]);
        $department = DB::table('departments')->where('code','INFO')->value('id');
        DB::table('programs')->updateOrInsert(['code'=>'LINFO'], ['department_id'=>$department,'name'=>'Licence Informatique','created_at'=>$now,'updated_at'=>$now]);
        $program = DB::table('programs')->where('code','LINFO')->value('id');
        $year = DB::table('academic_years')->where('name','2026/2027')->value('id');
        DB::table('semesters')->updateOrInsert(['program_id'=>$program,'academic_year_id'=>$year,'number'=>3], ['name'=>'Semestre 3','created_at'=>$now,'updated_at'=>$now]);
        $semester = DB::table('semesters')->where(['program_id'=>$program,'number'=>3])->value('id');
        foreach ([['G1',32],['G2',28]] as [$name,$count]) DB::table('student_groups')->updateOrInsert(['semester_id'=>$semester,'name'=>$name], ['student_count'=>$count,'created_at'=>$now,'updated_at'=>$now]);
        foreach ([['Amphi A',80,'cours'],['Salle 101',40,'cours'],['Labo Réseau',35,'tp'],['Salle TD 2',35,'td']] as [$name,$capacity,$type]) DB::table('classrooms')->updateOrInsert(['name'=>$name], ['capacity'=>$capacity,'type'=>$type,'created_at'=>$now,'updated_at'=>$now]);
        foreach ([['Dr. Amina Idrissi','amina@planif-uni.test'],['M. Youssef Alaoui','youssef@planif-uni.test'],['Mme. Salma Benali','salma@planif-uni.test']] as [$name,$email]) User::firstOrCreate(['email'=>$email], ['name'=>$name,'password'=>Hash::make('password'),'role'=>'prof']);
        foreach ([['ALGO','Algorithmique',4],['BDD','Bases de données',4],['RESEAU','Réseaux',3],['WEB','Développement Web',3]] as [$code,$name,$hours]) DB::table('modules')->updateOrInsert(['code'=>$code], ['program_id'=>$program,'name'=>$name,'weekly_hours'=>$hours,'created_at'=>$now,'updated_at'=>$now]);
        $professors = DB::table('users')->whereIn('email',['amina@planif-uni.test','youssef@planif-uni.test','salma@planif-uni.test'])->pluck('id','email');
        $modules = DB::table('modules')->whereIn('code',['ALGO','BDD','RESEAU','WEB'])->pluck('id','code');
        $groups = DB::table('student_groups')->where('semester_id',$semester)->pluck('id','name');
        $plan = [['ALGO','G1','amina@planif-uni.test','cours',120,2],['BDD','G1','youssef@planif-uni.test','cours',120,2],['RESEAU','G1','salma@planif-uni.test','tp',120,1],['WEB','G1','amina@planif-uni.test','td',120,1],['ALGO','G2','amina@planif-uni.test','cours',120,2],['BDD','G2','youssef@planif-uni.test','cours',120,2],['RESEAU','G2','salma@planif-uni.test','tp',120,1],['WEB','G2','amina@planif-uni.test','td',120,1]];
        foreach ($plan as [$code,$group,$email,$type,$duration,$times]) DB::table('teaching_sessions')->updateOrInsert(['semester_id'=>$semester,'module_id'=>$modules[$code],'professor_id'=>$professors[$email],'student_group_id'=>$groups[$group],'type'=>$type], ['duration_minutes'=>$duration,'occurrences_per_week'=>$times,'created_at'=>$now,'updated_at'=>$now]);
    }
}
