<?php

namespace Tests\Feature;

use App\Models\{Classroom, StudentGroup, Timeslot, User};
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prouve que toutes les séances d'un même module (pour un groupe) se déroulent
 * dans LA MÊME salle : un module qui lit à l'heure 1 garde sa salle à l'heure 2.
 */
class SameRoomPerModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_keeps_same_room_for_all_its_sessions(): void
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'Département Test', 'code' => 'DT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'Programme Test', 'code' => 'PT', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // Module 3h/semaine → 2 créneaux de 1h30 sur des jours différents.
        $mod = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Algèbre', 'code' => 'ALG', 'type' => 'cours', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        $prof = User::create(['name' => 'Prof', 'email' => 'p@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);
        DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mod, 'created_at' => $now, 'updated_at' => $now]);

        DB::table('days')->insert(['name' => 'Monday', 'position' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('days')->insert(['name' => 'Tuesday', 'position' => 2, 'created_at' => $now, 'updated_at' => $now]);
        Timeslot::create(['name' => '09:00-10:30', 'starts_at' => '09:00', 'ends_at' => '10:30', 'position' => 1]);
        Timeslot::create(['name' => '10:30-12:00', 'starts_at' => '10:30', 'ends_at' => '12:00', 'position' => 2]);

        StudentGroup::create(['semester_id' => $sem, 'name' => 'G1', 'capacity' => 30]);

        // Plusieurs salles éligibles pour vérifier que le même module garde la sienne.
        Classroom::create(['name' => 'Salle 1', 'capacity' => 40, 'type' => 'cours']);
        Classroom::create(['name' => 'Salle 2', 'capacity' => 40, 'type' => 'cours']);
        Classroom::create(['name' => 'Salle 3', 'capacity' => 40, 'type' => 'cours']);

        $report = app(AutoGenerateTimetable::class)->generate($sem);
        $this->assertTrue($report['success'], json_encode($report['skipped_per_module']));

        $sessions = DB::table('timetable_sessions')->where('semester_id', $sem)->get();
        $this->assertCount(2, $sessions, 'Le module 3h avec des créneaux de 1h30 doit donner exactement 2 séances.');

        $rooms = $sessions->pluck('classroom_id')->unique();
        $this->assertCount(1, $rooms, 'Toutes les séances du module doivent être dans la même salle.');

        $roomName = DB::table('classrooms')->where('id', $rooms->first())->value('name');
        foreach ($sessions as $s) {
            $this->assertSame($roomName, DB::table('classrooms')->where('id', $s->classroom_id)->value('name'));
        }
    }

    public function test_same_room_kept_on_regeneration(): void
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'Département Test', 'code' => 'DT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'Programme Test', 'code' => 'PT', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $mod = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Algèbre', 'code' => 'ALG', 'type' => 'cours', 'weekly_hours' => 2, 'created_at' => $now, 'updated_at' => $now]);
        $prof = User::create(['name' => 'Prof', 'email' => 'p@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);
        DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mod, 'created_at' => $now, 'updated_at' => $now]);

        DB::table('days')->insert(['name' => 'Monday', 'position' => 1, 'created_at' => $now, 'updated_at' => $now]);
        Timeslot::create(['name' => '09:00-11:00', 'starts_at' => '09:00', 'ends_at' => '11:00', 'position' => 1]);
        StudentGroup::create(['semester_id' => $sem, 'name' => 'G1', 'capacity' => 30]);

        Classroom::create(['name' => 'Salle A', 'capacity' => 40, 'type' => 'cours']);
        Classroom::create(['name' => 'Salle B', 'capacity' => 40, 'type' => 'cours']);

        $report = app(AutoGenerateTimetable::class)->generate($sem);
        $this->assertTrue($report['success']);

        $firstRoom = DB::table('timetable_sessions')->where('semester_id', $sem)->value('classroom_id');

        // Re-génération sans suppression : la même salle doit être réutilisée.
        $report2 = app(AutoGenerateTimetable::class)->generate($sem);
        $this->assertSame(0, $report2['sessions_generated'], 'La re-génération ne doit rien ajouter.');

        $secondRoom = DB::table('timetable_sessions')->where('semester_id', $sem)->value('classroom_id');
        $this->assertSame($firstRoom, $secondRoom, 'La re-génération doit conserver la salle du module.');
    }
}
