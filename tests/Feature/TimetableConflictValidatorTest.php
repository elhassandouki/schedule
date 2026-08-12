<?php
namespace Tests\Feature;

use App\Models\{Classroom, Department, Program, SchoolDay, StudentGroup, Semester, Module, Timeslot, TimetableSession, User};
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
        $semester = $this->semester();
        $module = Module::create([
            'program_id' => $semester->program_id,
            'semester_id' => $semester->id,
            'name' => 'Maths',
            'code' => 'MTH-' . \Illuminate\Support\Str::random(4),
            'weekly_hours' => 1,
        ]);
        $teacher = $this->professor('Ada');
        $otherTeacher = $this->professor('Grace');
        foreach ([$teacher, $otherTeacher] as $p) {
            $p->modules()->attach($module->id);
            DB::table('professor_availabilities')->insert([
                'professor_id' => $p->id,
                'day_of_week' => 1,
                'start_minute' => 480,
                'end_minute' => 1020,
                'available' => true,
            ]);
        }
        $room = Classroom::create(['name' => 'A1']);
        $otherRoom = Classroom::create(['name' => 'B1']);
        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'CS-1']);
        $otherGroup = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'CS-2']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $slot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        $attributes = ['module_id' => $module->id, 'professor_id' => $teacher->id, 'classroom_id' => $room->id, 'student_group_id' => $group->id, 'semester_id' => $semester->id, 'day_id' => $day->id, 'timeslot_id' => $slot->id];
        TimetableSession::create($attributes);
        $validator = new TimetableConflictValidator;

        foreach ([
            ['changes' => ['professor_id' => $teacher->id, 'classroom_id' => $otherRoom->id, 'student_group_id' => $otherGroup->id], 'field' => 'professor_id'],
            ['changes' => ['professor_id' => $otherTeacher->id, 'classroom_id' => $room->id, 'student_group_id' => $otherGroup->id], 'field' => 'classroom_id'],
            ['changes' => ['professor_id' => $otherTeacher->id, 'classroom_id' => $otherRoom->id, 'student_group_id' => $group->id], 'field' => 'student_group_id'],
        ] as $case) {
            $changes = $case['changes'];
            $expectedField = $case['field'];
            try {
                $validator->validate(array_merge($attributes, $changes));
                $this->fail('Expected a timetable conflict.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($expectedField, $exception->errors());
            }
        }
    }

    public function test_it_allows_a_session_in_a_different_slot_and_ignores_the_session_being_edited(): void
    {
        $semester = $this->semester();
        $module = Module::create([
            'program_id' => $semester->program_id,
            'semester_id' => $semester->id,
            'name' => 'Maths',
            'code' => 'MTH-' . \Illuminate\Support\Str::random(4),
            'weekly_hours' => 1,
        ]);
        $teacher = $this->professor('Ada');
        $teacher->modules()->attach($module->id);
        DB::table('professor_availabilities')->insert([
            'professor_id' => $teacher->id,
            'day_of_week' => 1,
            'start_minute' => 480,
            'end_minute' => 1020,
            'available' => true,
        ]);
        $room = Classroom::create(['name' => 'A1']);
        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'S1']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $first = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        $second = Timeslot::create(['name' => '10:00-12:00', 'starts_at' => '10:00', 'ends_at' => '12:00', 'position' => 2]);
        $session = TimetableSession::create(['module_id' => $module->id, 'professor_id' => $teacher->id, 'classroom_id' => $room->id, 'student_group_id' => $group->id, 'semester_id' => $semester->id, 'day_id' => $day->id, 'timeslot_id' => $first->id]);
        $attributes = $session->only(['module_id', 'professor_id', 'classroom_id', 'student_group_id', 'day_id', 'semester_id']);
        (new TimetableConflictValidator)->validate($attributes + ['timeslot_id' => $second->id]);
        (new TimetableConflictValidator)->validate($attributes + ['timeslot_id' => $first->id], $session);
        $this->assertTrue(true);
    }

    public function test_database_unique_constraint_blocks_a_teacher_double_booking(): void
    {
        $semester = $this->semester();
        $module = Module::create([
            'program_id' => $semester->program_id,
            'semester_id' => $semester->id,
            'name' => 'Maths',
            'code' => 'MTH-' . \Illuminate\Support\Str::random(4),
            'weekly_hours' => 1,
        ]);
        $teacher = $this->professor('Ada');
        $teacher->modules()->attach($module->id);
        DB::table('professor_availabilities')->insert([
            'professor_id' => $teacher->id,
            'day_of_week' => 1,
            'start_minute' => 480,
            'end_minute' => 1020,
            'available' => true,
        ]);
        $room = Classroom::create(['name' => 'A1']);
        $otherRoom = Classroom::create(['name' => 'B1']);
        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'S1']);
        $otherGroup = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'S2']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $slot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        TimetableSession::create(['semester_id' => $semester->id, 'module_id' => $module->id, 'professor_id' => $teacher->id, 'classroom_id' => $room->id, 'student_group_id' => $group->id, 'day_id' => $day->id, 'timeslot_id' => $slot->id]);
        $this->expectException(QueryException::class);
        TimetableSession::create(['semester_id' => $semester->id, 'module_id' => $module->id, 'professor_id' => $teacher->id, 'classroom_id' => $otherRoom->id, 'student_group_id' => $otherGroup->id, 'day_id' => $day->id, 'timeslot_id' => $slot->id]);
    }

    public function test_database_allows_a_reusable_group_in_a_different_semester_slot(): void
    {
        $semester1 = $this->semester();
        $module = Module::create([
            'program_id' => $semester1->program_id,
            'semester_id' => $semester1->id,
            'name' => 'Maths',
            'code' => 'MTH-' . \Illuminate\Support\Str::random(4),
            'weekly_hours' => 1,
        ]);
        $teacher = $this->professor('Ada');
        $teacher->modules()->attach($module->id);
        DB::table('professor_availabilities')->insert([
            'professor_id' => $teacher->id,
            'day_of_week' => 1,
            'start_minute' => 480,
            'end_minute' => 1020,
            'available' => true,
        ]);
        $room = Classroom::create(['name' => 'A1']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $slot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        $group = StudentGroup::create(['semester_id' => $semester1->id, 'name' => 'S1']);
        $semester2 = Semester::create(['program_id' => $semester1->program_id, 'academic_year_id' => $semester1->academic_year_id, 'name' => 'S2', 'number' => 2]);
        $data = ['module_id' => $module->id, 'professor_id' => $teacher->id, 'classroom_id' => $room->id, 'student_group_id' => $group->id, 'day_id' => $day->id, 'timeslot_id' => $slot->id];
        TimetableSession::create($data + ['semester_id' => $semester1->id]);
        TimetableSession::create($data + ['semester_id' => $semester2->id]);
        $this->assertSame(2, TimetableSession::count());
    }

    private function semester(int $number = 1): Semester
    {
        $department = Department::create(['name' => 'Science', 'code' => 'SCI']);
        $program = Program::create(['department_id' => $department->id, 'name' => 'Licence', 'code' => 'LIC']);
        $yearId = DB::table('academic_years')->insertGetId(['name' => '2026/2027', 'created_at' => now(), 'updated_at' => now()]);
        return Semester::create(['program_id' => $program->id, 'academic_year_id' => $yearId, 'name' => 'S' . $number, 'number' => $number]);
    }

    private function professor(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower($name) . '_' . \Illuminate\Support\Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
        ]);
    }
}
