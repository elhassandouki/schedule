<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnifiedDemoSeeder extends Seeder
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
            'department_id' => null,
            'program_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prof1_user = $this->insertOnce('users', ['email' => 'alice@school.local'], [
            'name' => 'Dr. Alice Martin',
            'email' => 'alice@school.local',
            'role' => 'prof',
            'password' => bcrypt('password'),
            'department_id' => null,
            'program_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prof2_user = $this->insertOnce('users', ['email' => 'bob@school.local'], [
            'name' => 'Prof. Bob Chen',
            'email' => 'bob@school.local',
            'role' => 'prof',
            'password' => bcrypt('password'),
            'department_id' => null,
            'program_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prof3_user = $this->insertOnce('users', ['email' => 'carol@school.local'], [
            'name' => 'Prof. Carol White',
            'email' => 'carol@school.local',
            'role' => 'prof',
            'password' => bcrypt('password'),
            'department_id' => null,
            'program_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // NOTE: dept1 (Informatique) is declared before users to allow
        // the department chief to be linked to it. We update after.
        // ════════════════════════════════════════════════════════════
        $chef_user = $this->insertOnce('users', ['email' => 'chef@school.local'], [
            'name' => 'Chef Département',
            'email' => 'chef@school.local',
            'role' => 'chef_departement',
            'password' => bcrypt('password'),
            'department_id' => null,
            'program_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 2. DEPARTMENTS
        // ════════════════════════════════════════════════════════════
        $dept1 = $this->insertOnce('departments', ['name' => 'Informatique'], [
            'name' => 'Informatique',
            'code' => 'INF',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $dept2 = $this->insertOnce('departments', ['name' => 'Mathématiques'], [
            'name' => 'Mathématiques',
            'code' => 'MATH',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // Link the department chief to Informatique (declared above).
        // ════════════════════════════════════════════════════════════
        if ($chef_user) {
            DB::table('users')->where('id', $chef_user)->update(['department_id' => $dept1, 'updated_at' => $now]);
        }

        // ════════════════════════════════════════════════════════════
        // 3. PROGRAMS (Filières)
        // ════════════════════════════════════════════════════════════
        $prog1 = $this->insertOnce('programs', ['code' => 'LIC-INF'], [
            'department_id' => $dept1,
            'name' => 'Licence Informatique',
            'code' => 'LIC-INF',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prog2 = $this->insertOnce('programs', ['code' => 'MASTER-IA'], [
            'department_id' => $dept1,
            'name' => 'Master IA',
            'code' => 'MASTER-IA',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prog3 = $this->insertOnce('programs', ['code' => 'LIC-MATH'], [
            'department_id' => $dept2,
            'name' => 'Licence Mathématiques',
            'code' => 'LIC-MATH',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 3b. ACADEMIC YEAR (required by semesters.academic_year_id NOT NULL)
        // ════════════════════════════════════════════════════════════
        $academicYearId = $this->insertOnce('academic_years', ['name' => '2026/2027'], [
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 4. SEMESTERS
        // ════════════════════════════════════════════════════════════
        $sem1_1 = DB::table('semesters')->insertGetId([
            'program_id' => $prog1,
            'academic_year_id' => $academicYearId,
            'name' => 'Semestre 1',
            'number' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sem1_2 = DB::table('semesters')->insertGetId([
            'program_id' => $prog1,
            'academic_year_id' => $academicYearId,
            'name' => 'Semestre 2',
            'number' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sem2_2 = DB::table('semesters')->insertGetId([
            'program_id' => $prog3,
            'academic_year_id' => $academicYearId,
            'name' => 'Semestre Maths 1',
            'number' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sem2_1 = DB::table('semesters')->insertGetId([
            'program_id' => $prog2,
            'academic_year_id' => $academicYearId,
            'name' => 'Semestre 1',
            'number' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 5. MODULES (Course Definitions)
        // ════════════════════════════════════════════════════════════
        $mod1 = DB::table('modules')->insertGetId([
            'program_id' => $prog1,
            'semester_id' => $sem1_1,
            'name' => 'Introduction Programmation',
            'code' => 'INF101',
            'weekly_hours' => 6,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $mod2 = DB::table('modules')->insertGetId([
            'program_id' => $prog1,
            'semester_id' => $sem1_1,
            'name' => 'Mathématiques Discrètes',
            'code' => 'INF102',
            'weekly_hours' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $mod3 = DB::table('modules')->insertGetId([
            'program_id' => $prog1,
            'semester_id' => $sem1_2,
            'name' => 'Structures de Données',
            'code' => 'INF201',
            'weekly_hours' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $mod4 = DB::table('modules')->insertGetId([
            'program_id' => $prog2,
            'semester_id' => $sem2_1,
            'name' => 'Machine Learning Avancé',
            'code' => 'MAI301',
            'weekly_hours' => 6,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 6. STUDENT GROUPS
        // ════════════════════════════════════════════════════════════
        $sg1_1 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem1_1,
            'name' => 'Groupe A',
            'student_count' => 30,
            'capacity' => 30,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sg1_2 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem1_1,
            'name' => 'Groupe B',
            'student_count' => 28,
            'capacity' => 28,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sg2_1 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem1_2,
            'name' => 'Groupe Unique',
            'student_count' => 58,
            'capacity' => 60,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sg3_1 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem2_1,
            'name' => 'Master Promo 2025',
            'student_count' => 20,
            'capacity' => 20,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 7. PROFESSOR ↔ MODULE ASSIGNMENTS (who teaches what)
        // ════════════════════════════════════════════════════════════
        // Professors are users with role 'prof' assigned to modules via the
        // pivot table professor_module (the legacy teachers/subjects schema
        // was removed).
        $this->insertOnce('professor_module', ['professor_id' => $prof1_user, 'module_id' => $mod1], []);
        $this->insertOnce('professor_module', ['professor_id' => $prof2_user, 'module_id' => $mod2], []);
        $this->insertOnce('professor_module', ['professor_id' => $prof1_user, 'module_id' => $mod3], []);
        $this->insertOnce('professor_module', ['professor_id' => $prof3_user, 'module_id' => $mod4], []);

        // ════════════════════════════════════════════════════════════
        // 7c. GROUP STUDY CONDITIONS (default: available Mon–Fri 08:00–17:00,
        //     max 6 hours/day, max 60 minutes gap)
        // ════════════════════════════════════════════════════════════
        foreach ([$sg1_1, $sg1_2, $sg2_1, $sg3_1] as $gid) {
            for ($d = 1; $d <= 5; $d++) {
                $this->insertOnce('group_study_conditions', [
                    'student_group_id' => $gid,
                    'day_of_week' => $d,
                ], [
                    'start_minute' => 8 * 60,
                    'end_minute' => 17 * 60,
                    'max_daily_minutes' => 360,
                    'max_gap_minutes' => 60,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // ════════════════════════════════════════════════════════════
        // 7b. PROFESSOR AVAILABILITIES (default: available Mon–Fri 08:00–17:00)
        // ════════════════════════════════════════════════════════════
        foreach ([$prof1_user, $prof2_user, $prof3_user] as $pid) {
            for ($d = 1; $d <= 5; $d++) {
                $this->insertOnce('professor_availabilities', [
                    'professor_id' => $pid,
                    'day_of_week' => $d,
                ], [
                    'start_minute' => 8 * 60,
                    'end_minute' => 17 * 60,
                    'available' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // ════════════════════════════════════════════════════════════
        // 8. DAYS (Monday–Friday)
        // ════════════════════════════════════════════════════════════
        $dayMon = DB::table('days')->insertGetId([
            'name' => 'Monday',
            'position' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $dayTue = DB::table('days')->insertGetId([
            'name' => 'Tuesday',
            'position' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $dayWed = DB::table('days')->insertGetId([
            'name' => 'Wednesday',
            'position' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $dayThu = DB::table('days')->insertGetId([
            'name' => 'Thursday',
            'position' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $dayFri = DB::table('days')->insertGetId([
            'name' => 'Friday',
            'position' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 11. TIMESLOTS (Fixed Time Windows)
        // ════════════════════════════════════════════════════════════
        $ts1 = DB::table('timeslots')->insertGetId([
            'name' => '08:00-10:00',
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'position' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ts2 = DB::table('timeslots')->insertGetId([
            'name' => '10:00-12:00',
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'position' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ts3 = DB::table('timeslots')->insertGetId([
            'name' => '13:00-15:00',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'position' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ts4 = DB::table('timeslots')->insertGetId([
            'name' => '15:00-17:00',
            'starts_at' => '15:00',
            'ends_at' => '17:00',
            'position' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // 12. CLASSROOMS
        // ════════════════════════════════════════════════════════════
        $room1 = DB::table('classrooms')->insertGetId([
            'name' => 'Amphi A',
            'capacity' => 100,
            'type' => 'amphitheatre',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $room2 = DB::table('classrooms')->insertGetId([
            'name' => 'Salle 101',
            'capacity' => 40,
            'type' => 'classroom',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $room3 = DB::table('classrooms')->insertGetId([
            'name' => 'Salle 102',
            'capacity' => 40,
            'type' => 'classroom',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $room4 = DB::table('classrooms')->insertGetId([
            'name' => 'Labo Info',
            'capacity' => 30,
            'type' => 'lab',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ════════════════════════════════════════════════════════════
        // SUCCESS MESSAGE
        // ════════════════════════════════════════════════════════════
        $this->command->info('✅ Unified demo data seeded successfully!');
        $this->command->line('');
        $this->command->line('Structure created:');
        $this->command->line('  - 2 departments, 2 programs, 3 semesters');
        $this->command->line('  - 4 modules, 4 student groups');
        $this->command->line('  - 5 users (1 admin, 3 profs, 1 chef)');
        $this->command->line('  - 3 professor availabilities (Mon–Fri 08:00–17:00)');
        $this->command->line('  - 5 days, 4 timeslots, 4 classrooms');
        $this->command->line('  - 0 timetable sessions (ready for auto-generation)');
        $this->command->line('');
        $this->command->line('Next step:');
        $this->command->line('  php artisan timetable:generate 1  (generates for Semester 1, Program 1)');
        $this->command->line('  php artisan timetable:generate 2  (generates for Semester 2, Program 1)');
        $this->command->line('  php artisan timetable:generate 3  (generates for Semester 1, Program 2 / Master)');
        $this->command->line('');
        $this->command->line('Test logins (password: "password"):');
        $this->command->line('  admin@school.local (super_admin)');
        $this->command->line('  alice@school.local (prof, Dr. Alice Martin)');
        $this->command->line('  bob@school.local (prof, Prof. Bob Chen)');
        $this->command->line('  carol@school.local (prof, Prof. Carol White)');
        $this->command->line('  chef@school.local (chef_departement)');
    }
    private function insertOnce(string $table, array $unique, array $data): int
    {
        $existing = DB::table($table)->where($unique)->value('id');
        if ($existing) {
            return (int) $existing;
        }
        return (int) DB::table($table)->insertGetId(array_merge($unique, $data));
    }
}
