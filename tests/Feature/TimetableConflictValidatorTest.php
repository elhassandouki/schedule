<?php
namespace Tests\Feature;

use App\Models\{Classroom, Department, Program, SchoolDay, StudentGroup, Semester, Subject, Teacher, Timeslot, TimetableSession};
use Illuminate\Support\Facades\DB;
use App\Services\TimetableConflictValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class TimetableConflictValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_teacher_room_and_group_conflicts(): void
    {
        $teacher = Teacher::create(['name' => 'Ada']);
        $otherTeacher = Teacher::create(['name' => 'Grace']);
        $room = Classroom::create(['name' => 'A1']);
        $otherRoom = Classroom::create(['name' => 'B1']);
        $semester = $this->semester();
        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'CS-1']);
        $otherGroup = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'CS-2']);
        $subject = Subject::create(['teacher_id' => $teacher->id, 'name' => 'Maths', 'code' => 'MTH']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $slot = Timeslot::create(['name' => '08:00–10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        $attributes = ['subject_id'=>$subject->id, 'teacher_id'=>$teacher->id, 'classroom_id'=>$room->id, 'student_group_id'=>$group->id, 'semester_id'=>$semester->id, 'day_id'=>$day->id, 'timeslot_id'=>$slot->id];
        TimetableSession::create($attributes);
        $validator = new TimetableConflictValidator;

        foreach ([
            ['changes'=>['teacher_id'=>$teacher->id, 'classroom_id'=>$otherRoom->id, 'student_group_id'=>$otherGroup->id], 'field'=>'teacher_id'],
            ['changes'=>['teacher_id'=>$otherTeacher->id, 'classroom_id'=>$room->id, 'student_group_id'=>$otherGroup->id], 'field'=>'classroom_id'],
            ['changes'=>['teacher_id'=>$otherTeacher->id, 'classroom_id'=>$otherRoom->id, 'student_group_id'=>$group->id], 'field'=>'student_group_id'],
        ] as $case) {
            $changes = $case['changes']; $expectedField = $case['field'];
            try { $validator->validate(array_merge($attributes, $changes)); $this->fail('Expected a timetable conflict.'); }
            catch (ValidationException $exception) { $this->assertArrayHasKey($expectedField, $exception->errors()); }
        }
    }

    public function test_it_allows_a_session_in_a_different_slot_and_ignores_the_session_being_edited(): void
    {
        $semester = $this->semester();
        $teacher = Teacher::create(['name'=>'Ada']); $room = Classroom::create(['name'=>'A1']); $group = StudentGroup::create(['semester_id'=>$semester->id,'name'=>'S1']); $subject = Subject::create(['teacher_id'=>$teacher->id,'name'=>'Maths','code'=>'MTH']);
        $day = SchoolDay::create(['name'=>'Monday','position'=>1]);
        $first = Timeslot::create(['name'=>'08:00','starts_at'=>'08:00','ends_at'=>'10:00','position'=>1]); $second = Timeslot::create(['name'=>'10:00','starts_at'=>'10:00','ends_at'=>'12:00','position'=>2]);
        $session = TimetableSession::create(['subject_id'=>$subject->id,'teacher_id'=>$teacher->id,'classroom_id'=>$room->id,'student_group_id'=>$group->id,'semester_id'=>$semester->id,'day_id'=>$day->id,'timeslot_id'=>$first->id]);
        $attributes = $session->only(['subject_id','teacher_id','classroom_id','student_group_id','day_id','semester_id']);
        (new TimetableConflictValidator)->validate($attributes + ['timeslot_id'=>$second->id]);
        (new TimetableConflictValidator)->validate($attributes + ['timeslot_id'=>$first->id], $session);
        $this->assertTrue(true);
    }

    public function test_database_unique_constraint_blocks_a_teacher_double_booking(): void
    {
        $semester = $this->semester();
        $teacher = Teacher::create(['name'=>'Ada']); $room = Classroom::create(['name'=>'A1']); $otherRoom = Classroom::create(['name'=>'B1']); $group = StudentGroup::create(['semester_id'=>$semester->id,'name'=>'S1']); $otherGroup = StudentGroup::create(['semester_id'=>$semester->id,'name'=>'S2']); $subject = Subject::create(['teacher_id'=>$teacher->id,'name'=>'Maths','code'=>'MTH']);
        $day = SchoolDay::create(['name'=>'Monday','position'=>1]); $slot = Timeslot::create(['name'=>'08:00','starts_at'=>'08:00','ends_at'=>'10:00','position'=>1]);
        TimetableSession::create(['semester_id'=>$semester->id,'subject_id'=>$subject->id,'teacher_id'=>$teacher->id,'classroom_id'=>$room->id,'student_group_id'=>$group->id,'day_id'=>$day->id,'timeslot_id'=>$slot->id]);
        $this->expectException(QueryException::class);
        TimetableSession::create(['semester_id'=>$semester->id,'subject_id'=>$subject->id,'teacher_id'=>$teacher->id,'classroom_id'=>$otherRoom->id,'student_group_id'=>$otherGroup->id,'day_id'=>$day->id,'timeslot_id'=>$slot->id]);
    }

    public function test_database_allows_a_reusable_group_in_a_different_semester_slot(): void
    {
        $teacher = Teacher::create(['name'=>'Ada']); $room = Classroom::create(['name'=>'A1']); $subject = Subject::create(['teacher_id'=>$teacher->id,'name'=>'Maths','code'=>'MTH']);
        $day = SchoolDay::create(['name'=>'Monday','position'=>1]); $slot = Timeslot::create(['name'=>'08:00','starts_at'=>'08:00','ends_at'=>'10:00','position'=>1]);
        $first = $this->semester();
        $group = StudentGroup::create(['semester_id'=>$first->id,'name'=>'S1']);
        $second = Semester::create(['program_id'=>$first->program_id,'academic_year_id'=>$first->academic_year_id,'name'=>'S2','number'=>2]);
        $data = ['subject_id'=>$subject->id,'teacher_id'=>$teacher->id,'classroom_id'=>$room->id,'student_group_id'=>$group->id,'day_id'=>$day->id,'timeslot_id'=>$slot->id];
        TimetableSession::create($data + ['semester_id'=>$first->id]);
        TimetableSession::create($data + ['semester_id'=>$second->id]);
        $this->assertSame(2, TimetableSession::count());
    }

    private function semester(int $number = 1): Semester
    {
        $department = Department::create(['name'=>'Science','code'=>'SCI']);
        $program = Program::create(['department_id'=>$department->id,'name'=>'Licence','code'=>'LIC']);
        $yearId = DB::table('academic_years')->insertGetId(['name'=>'2026/2027','created_at'=>now(),'updated_at'=>now()]);
        return Semester::create(['program_id'=>$program->id,'academic_year_id'=>$yearId,'name'=>'S'.$number,'number'=>$number]);
    }
}
