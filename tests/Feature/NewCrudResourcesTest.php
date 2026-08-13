<?php
namespace Tests\Feature;
class NewCrudResourcesTest extends \Tests\TestCase {
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(\Database\Seeders\UnifiedDemoSeeder::class); }
    public function test_new_crud_resources_store_update_destroy(): void {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)->actingAs($this->admin());
        $prof = \App\Models\User::where('role','prof')->first();
        $this->withoutExceptionHandling();
        // store availability
        $res = $this->post(route('crud.store','disponibilites-profs'),[
            'professor_id'=>$prof->id,'day_of_week'=>3,'start_minute'=>480,'end_minute'=>600,'available'=>1
        ]);
        $res->assertRedirect();
        $id = \DB::table('professor_availabilities')->orderByDesc('id')->value('id');
        $this->assertNotNull($id);
        // update
        $this->put(route('crud.update',['disponibilites-profs',$id]),[
            'professor_id'=>$prof->id,'day_of_week'=>3,'start_minute'=>510,'end_minute'=>720,'available'=>1
        ])->assertRedirect();
        $this->assertEquals(510, \DB::table('professor_availabilities')->where('id',$id)->value('start_minute'));
        // store condition
        $group = \DB::table('student_groups')->first();
        $this->post(route('crud.store','conditions-groupes'),[
            'student_group_id'=>$group->id,'day_of_week'=>6,'start_minute'=>480,'end_minute'=>600,'max_daily_minutes'=>300,'max_gap_minutes'=>45
        ])->assertRedirect();
        $cid = \DB::table('group_study_conditions')->orderByDesc('id')->value('id');
        // destroy
        $this->delete(route('crud.destroy',['conditions-groupes',$cid]))->assertRedirect();
        $this->assertNull(\DB::table('group_study_conditions')->find($cid));
        // destroy avail
        $this->delete(route('crud.destroy',['disponibilites-profs',$id]))->assertRedirect();
    }
    public function test_group_semester_must_belong_to_program(): void {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)->actingAs($this->admin());
        // Pick two different programs and a semester that belongs to the other program
        $programs = \DB::table('programs')->orderBy('id')->pluck('id')->all();
        $this->assertGreaterThanOrEqual(2, count($programs));
        $programA = $programs[0];
        $programB = $programs[1];
        $otherSemester = \DB::table('semesters')->where('program_id', $programB)->value('id');
        $this->assertNotNull($otherSemester);
        $group = \DB::table('student_groups')->first();
        $this->put(route('crud.update',['groupes',$group->id]),[
            'program_id'=>$programA,'semester_id'=>$otherSemester,
            'name'=>'X','capacity'=>10,'student_count'=>5,'max_daily_minutes'=>360,
        ])->assertStatus(422);
    }
    public function test_delete_generation_removes_history_and_sessions(): void {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)->actingAs($this->admin());
        // Create a history record
        $semester = \DB::table('semesters')->first();
        $id = \DB::table('schedule_histories')->insertGetId([
            'semester_id' => $semester->id,
            'name' => 'Test Generation',
            'status' => 'generated',
            'generated_sessions_count' => 1,
            'skipped_sessions_count' => 0,
            'generated_by_user_id' => \App\Models\User::where('email','admin@school.local')->value('id'),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(5),
        ]);
        // Create a fake session within that window
        \DB::table('timetable_sessions')->insert([
            'semester_id' => $semester->id,
            'module_id' => \DB::table('modules')->first()->id,
            'student_group_id' => \DB::table('student_groups')->first()->id,
            'professor_id' => \App\Models\User::where('role','prof')->value('id'),
            'classroom_id' => \DB::table('classrooms')->first()->id,
            'day_id' => \DB::table('days')->first()->id,
            'timeslot_id' => \DB::table('timeslots')->first()->id,
            'created_at' => now()->subMinutes(8),
            'updated_at' => now()->subMinutes(8),
        ]);
        $beforeSessions = \DB::table('timetable_sessions')->count();
        $res = $this->delete(route('timetable.generate.destroy', $id));
        $res->assertRedirect();
        $this->assertNull(\DB::table('schedule_histories')->find($id));
        $this->assertEquals($beforeSessions - 1, \DB::table('timetable_sessions')->count());
    }
    private function admin(): \App\Models\User { return \App\Models\User::where('email','admin@school.local')->first(); }
}
