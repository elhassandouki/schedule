<?php
namespace Tests\Feature;

use App\Models\{Classroom, SchoolDay, Section, Subject, Teacher, Timeslot, TimetableSession};
use App\Services\TimetableConflictValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TimetableConflictValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_teacher_room_and_section_conflicts(): void
    {
        $teacher = Teacher::create(['name' => 'Ada']);
        $otherTeacher = Teacher::create(['name' => 'Grace']);
        $room = Classroom::create(['name' => 'A1']);
        $otherRoom = Classroom::create(['name' => 'B1']);
        $section = Section::create(['name' => 'CS-1']);
        $otherSection = Section::create(['name' => 'CS-2']);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MTH']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $slot = Timeslot::create(['name' => '08:00–10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        $attributes = ['subject_id'=>$subject->id, 'teacher_id'=>$teacher->id, 'classroom_id'=>$room->id, 'section_id'=>$section->id, 'day_id'=>$day->id, 'timeslot_id'=>$slot->id];
        TimetableSession::create($attributes);
        $validator = new TimetableConflictValidator;

        foreach ([
            ['changes'=>['teacher_id'=>$teacher->id, 'classroom_id'=>$otherRoom->id, 'section_id'=>$otherSection->id], 'field'=>'teacher_id'],
            ['changes'=>['teacher_id'=>$otherTeacher->id, 'classroom_id'=>$room->id, 'section_id'=>$otherSection->id], 'field'=>'classroom_id'],
            ['changes'=>['teacher_id'=>$otherTeacher->id, 'classroom_id'=>$otherRoom->id, 'section_id'=>$section->id], 'field'=>'section_id'],
        ] as $case) {
            $changes = $case['changes']; $expectedField = $case['field'];
            try { $validator->validate(array_merge($attributes, $changes)); $this->fail('Expected a timetable conflict.'); }
            catch (ValidationException $exception) { $this->assertArrayHasKey($expectedField, $exception->errors()); }
        }
    }

    public function test_it_allows_a_session_in_a_different_slot_and_ignores_the_session_being_edited(): void
    {
        $teacher = Teacher::create(['name'=>'Ada']); $room = Classroom::create(['name'=>'A1']); $section = Section::create(['name'=>'S1']); $subject = Subject::create(['name'=>'Maths','code'=>'MTH']);
        $day = SchoolDay::create(['name'=>'Monday','position'=>1]);
        $first = Timeslot::create(['name'=>'08:00','starts_at'=>'08:00','ends_at'=>'10:00','position'=>1]); $second = Timeslot::create(['name'=>'10:00','starts_at'=>'10:00','ends_at'=>'12:00','position'=>2]);
        $session = TimetableSession::create(['subject_id'=>$subject->id,'teacher_id'=>$teacher->id,'classroom_id'=>$room->id,'section_id'=>$section->id,'day_id'=>$day->id,'timeslot_id'=>$first->id]);
        $attributes = $session->only(['subject_id','teacher_id','classroom_id','section_id','day_id']);
        (new TimetableConflictValidator)->validate($attributes + ['timeslot_id'=>$second->id]);
        (new TimetableConflictValidator)->validate($attributes + ['timeslot_id'=>$first->id], $session);
        $this->assertTrue(true);
    }
}
