<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerationRespectsConditionsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(\Database\Seeders\UnifiedDemoSeeder::class); }

    public function test_group_restricted_to_monday_morning(): void
    {
        $group = \DB::table('student_groups')->first();
        // Réinitialiser : groupe ne peut étudier QUE le lundi (day 1) créneau 1 (8-10)
        \DB::table('group_study_conditions')->where('student_group_id', $group->id)->delete();
        \DB::table('group_study_conditions')->insert([
            ['student_group_id'=>$group->id,'day_of_week'=>1,'start_minute'=>480,'end_minute'=>600,'max_daily_minutes'=>360,'created_at'=>now(),'updated_at'=>now()],
        ]);
        // Prof assigné au module 1 du semestre 1 : disponibilité uniquement le mardi (day 2)
        $module = \DB::table('modules')->where('semester_id',1)->first();
        $assigned = \DB::table('professor_module')->where('module_id',$module->id)->first();
        \DB::table('professor_availabilities')->where('professor_id',$assigned->professor_id)->delete();
        \DB::table('professor_availabilities')->insert([
            ['professor_id'=>$assigned->professor_id,'day_of_week'=>2,'start_minute'=>480,'end_minute'=>600,'available'=>true,'created_at'=>now(),'updated_at'=>now()],
        ]);
        $gen = new \App\Services\AutoGenerateTimetable();
        $r = $gen->generate(1);
        // Aucune session ne doit tomber un jour autre que lundi pour ce groupe
        $sessions = \DB::table('timetable_sessions')->where('student_group_id',$group->id)->get();
        foreach ($sessions as $s) {
            $day = \DB::table('days')->where('id',$s->day_id)->value('position');
            $this->assertEquals(1, $day, 'Session hors jour autorisé du groupe');
        }
        // Le module ne peut pas être placé (prof dispo mardi, groupe lundi) → skipped
        $this->assertTrue(str_contains(json_encode($r), "Not enough available slots"));
    }

    public function test_sessions_respect_group_conditions_on_generated_data(): void
    {
        $gen = new \App\Services\AutoGenerateTimetable();
        foreach (\DB::table('semesters')->pluck('id') as $sid) $gen->generate($sid);
        foreach (\DB::table('timetable_sessions')->get() as $s) {
            $dayPos = \DB::table('days')->where('id',$s->day_id)->value('position');
            $slot = \DB::table('timeslots')->where('id',$s->timeslot_id)->first();
            $cond = \DB::table('group_study_conditions')->where('student_group_id',$s->student_group_id)->where('day_of_week',$dayPos)->first();
            if ($cond) {
                $start = (int) substr($slot->starts_at,0,2)*60+(int) substr($slot->starts_at,3,2);
                $end   = (int) substr($slot->ends_at,0,2)*60+(int) substr($slot->ends_at,3,2);
                $this->assertTrue($cond->start_minute <= $start && $cond->end_minute >= $end,
                    "Session {$s->id} hors plage groupe (day $dayPos)");
            }
            $profAvail = \DB::table('professor_availabilities')->where('professor_id',$s->professor_id)->where('day_of_week',$dayPos)->first();
            if ($profAvail) {
                $start = (int) substr($slot->starts_at,0,2)*60+(int) substr($slot->starts_at,3,2);
                $end   = (int) substr($slot->ends_at,0,2)*60+(int) substr($slot->ends_at,3,2);
                $this->assertTrue($profAvail->available && $profAvail->start_minute <= $start && $profAvail->end_minute >= $end,
                    "Session {$s->id} hors disponibilité prof (day $dayPos)");
            }
        }
        $this->assertTrue(true);
    }
}
