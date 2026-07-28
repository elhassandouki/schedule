<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManualTimetableDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $program = DB::table('programs')->where('code', 'FLDESLA')->first();
        $yearId = DB::table('academic_years')->where('is_active', true)->value('id');
        $semester = $program && $yearId ? DB::table('semesters')->where(['program_id'=>$program->id, 'academic_year_id'=>$yearId, 'number'=>1])->first() : null;
        if (!$semester) return;

        foreach ([['Dr. Amina Sample', 'amina.sample@timetable.test'], ['Mr. Omar Sample', 'omar.sample@timetable.test']] as [$name, $email]) DB::table('teachers')->updateOrInsert(['email'=>$email], ['name'=>$name, 'created_at'=>$now, 'updated_at'=>$now]);
        $teachers = DB::table('teachers')->whereIn('email', ['amina.sample@timetable.test','omar.sample@timetable.test'])->pluck('id','email');
        foreach (['Group A', 'Group B'] as $name) DB::table('sections')->updateOrInsert(['program_id'=>$program->id, 'name'=>$name], ['capacity'=>35, 'created_at'=>$now, 'updated_at'=>$now]);
        $groups = DB::table('sections')->where('program_id',$program->id)->whereIn('name',['Group A','Group B'])->pluck('id','name');
        foreach ([['DEMO-READ', 'Reading comprehension', $teachers['amina.sample@timetable.test']], ['DEMO-GRAM', 'English grammar', $teachers['omar.sample@timetable.test']]] as [$code,$name,$teacherId]) DB::table('subjects')->updateOrInsert(['code'=>$code], ['semester_id'=>$semester->id,'teacher_id'=>$teacherId,'name'=>$name,'created_at'=>$now,'updated_at'=>$now]);
        foreach ([['Monday',1],['Tuesday',2],['Wednesday',3],['Thursday',4],['Friday',5]] as [$name,$position]) DB::table('days')->updateOrInsert(['name'=>$name],['position'=>$position,'created_at'=>$now,'updated_at'=>$now]);
        foreach ([['08:00–10:00','08:00','10:00',1],['10:00–12:00','10:00','12:00',2]] as [$name,$start,$end,$position]) DB::table('timeslots')->updateOrInsert(['starts_at'=>$start,'ends_at'=>$end],['name'=>$name,'position'=>$position,'created_at'=>$now,'updated_at'=>$now]);
        $subjects = DB::table('subjects')->whereIn('code', ['DEMO-READ','DEMO-GRAM'])->pluck('id','code');
        $dayId = DB::table('days')->where('name','Monday')->value('id'); $slots = DB::table('timeslots')->pluck('id','position'); $roomId = DB::table('classrooms')->orderBy('id')->value('id');
        if ($roomId) {
            DB::table('timetable_sessions')->updateOrInsert(['subject_id'=>$subjects['DEMO-READ'],'section_id'=>$groups['Group A']], ['semester_id'=>$semester->id,'teacher_id'=>$teachers['amina.sample@timetable.test'],'classroom_id'=>$roomId,'timeslot_id'=>$slots[1],'day_id'=>$dayId,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('timetable_sessions')->updateOrInsert(['subject_id'=>$subjects['DEMO-GRAM'],'section_id'=>$groups['Group A']], ['semester_id'=>$semester->id,'teacher_id'=>$teachers['omar.sample@timetable.test'],'classroom_id'=>$roomId,'timeslot_id'=>$slots[2],'day_id'=>$dayId,'created_at'=>$now,'updated_at'=>$now]);
        }
    }
}
