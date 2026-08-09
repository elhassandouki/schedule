<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentGroupsArchitectureSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ════════════════════════════════════════════════════════════
        // 1. USERS (Login Accounts)
        // ════════════════════════════════════════════════════════════
        $admin = $this->insertOnce('users', ['email' => 'admin@school.local'], [
            'name' => 'Admin Master',
            'email' => 'admin@school.local',
            'role' => 'super_admin',
            'password' => bcrypt('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prof1_user = $this->insertOnce('users', ['email' => 'prof1@school.local'], [
            'name' => 'Prof Alice Martin',
            'email' => 'prof1@school.local',
            'role' => 'prof',
            'password' => bcrypt('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prof2_user = $this->insertOnce('users', ['email' => 'prof2@school.local'], [
            'name' => 'Prof Bob Chen',
            'email' => 'prof2@school.local',
            'role' => 'prof',
            'password' => bcrypt('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prof3_user = $this->insertOnce('users', ['email' => 'prof3@school.local'], [
            'name' => 'Prof Carol White',
            'email' => 'prof3@school.local',
            'role' => 'prof',
            'password' => bcrypt('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 2. TEACHERS (Independent, linked to users)
        // ════════════════════════════════════════════════════════════
        $prof1 = DB::table('teachers')->insertGetId([
            'name' => 'Prof Alice Martin',
            'user_id' => $prof1_user,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prof2 = DB::table('teachers')->insertGetId([
            'name' => 'Prof Bob Chen',
            'user_id' => $prof2_user,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prof3 = DB::table('teachers')->insertGetId([
            'name' => 'Prof Carol White',
            'user_id' => $prof3_user,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 3. SUBJECTS (Independent - NO semester attached)
        // ════════════════════════════════════════════════════════════
        $subj1 = DB::table('subjects')->insertGetId([
            'name' => 'Programmation',
            'code' => 'PROG101',
            'teacher_id' => $prof1,
            'sessions_per_week' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subj2 = DB::table('subjects')->insertGetId([
            'name' => 'Mathématiques',
            'code' => 'MATH101',
            'teacher_id' => $prof2,
            'sessions_per_week' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subj3 = DB::table('subjects')->insertGetId([
            'name' => 'Bases de Données',
            'code' => 'DATA101',
            'teacher_id' => $prof3,
            'sessions_per_week' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subj4 = DB::table('subjects')->insertGetId([
            'name' => 'Algorithmes',
            'code' => 'ALGO101',
            'teacher_id' => $prof1,
            'sessions_per_week' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 4. ACADEMIC STRUCTURE (Departments, Programs, Semesters)
        // ════════════════════════════════════════════════════════════
        $dept1 = DB::table('departments')->insertGetId([
            'name' => 'Département Informatique',
            'code' => 'DEPT-INFO',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prog1 = DB::table('programs')->insertGetId([
            'department_id' => $dept1,
            'name' => 'Licence Informatique',
            'code' => 'LIC-INFO-001',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sem1 = DB::table('semesters')->insertGetId([
            'program_id' => $prog1,
            'name' => 'Semestre 1',
            'year' => 2025,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sem2 = DB::table('semesters')->insertGetId([
            'program_id' => $prog1,
            'name' => 'Semestre 2',
            'year' => 2025,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 5. STUDENT GROUPS (Tied to Semester)
        // ════════════════════════════════════════════════════════════
        $sg1 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem1,
            'name' => 'L1 Groupe A',
            'student_count' => 60,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sg2 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem1,
            'name' => 'L1 Groupe B',
            'student_count' => 55,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sg3 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem2,
            'name' => 'L2 Groupe A',
            'student_count' => 50,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 6. CLASSROOMS
        // ════════════════════════════════════════════════════════════
        $room1 = DB::table('classrooms')->insertGetId([
            'name' => 'Salle 101',
            'capacity' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $room2 = DB::table('classrooms')->insertGetId([
            'name' => 'Salle 102',
            'capacity' => 80,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $room3 = DB::table('classrooms')->insertGetId([
            'name' => 'Salle 103',
            'capacity' => 60,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $room4 = DB::table('classrooms')->insertGetId([
            'name' => 'Salle 104',
            'capacity' => 40,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 7. DAYS & TIMESLOTS
        // ════════════════════════════════════════════════════════════
        $days = [
            ['name' => 'Lundi', 'position' => 1],
            ['name' => 'Mardi', 'position' => 2],
            ['name' => 'Mercredi', 'position' => 3],
            ['name' => 'Jeudi', 'position' => 4],
            ['name' => 'Vendredi', 'position' => 5],
        ];

        foreach ($days as $day) {
            DB::table('days')->insertOrIgnore(array_merge($day, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $timeslots = [
            ['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1],
            ['name' => '10:00-12:00', 'starts_at' => '10:00', 'ends_at' => '12:00', 'position' => 2],
            ['name' => '14:00-16:00', 'starts_at' => '14:00', 'ends_at' => '16:00', 'position' => 3],
            ['name' => '16:00-18:00', 'starts_at' => '16:00', 'ends_at' => '18:00', 'position' => 4],
        ];

        foreach ($timeslots as $ts) {
            DB::table('timeslots')->insertOrIgnore(array_merge($ts, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command->info('✅ Seeded with student_groups architecture:');
        $this->command->line('  - Subjects (independent): 4');
        $this->command->line('  - Teachers: 3');
        $this->command->line('  - Student Groups: 3');
        $this->command->line('  - Classrooms: 4');
        $this->command->line('  - Days: 5');
        $this->command->line('  - Timeslots: 4');
        $this->command->line('');
        $this->command->line('Ready to generate timetables!');
        $this->command->line('Run: php artisan timetable:check-ready 1');
    }

    private function insertOnce($table, $uniqueColumn, $data)
    {
        if (is_array($uniqueColumn)) {
            $where = $uniqueColumn;
        } else {
            $where = [$uniqueColumn => $data[$uniqueColumn] ?? null];
        }

        $existing = DB::table($table)->where($where)->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table($table)->insertGetId($data);
    }
}
