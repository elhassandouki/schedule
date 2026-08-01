<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoTimetableSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // === Structure ===
        // Department → Program → Semester → Module
        //                              → StudentGroup
        //           → Teacher → Subject (assigned to a Semester)
        //           → Section (group of students in a program)
        //           → Day, Timeslot, Classroom (shared pool)
        //           → TimetableSession (day, timeslot, teacher, subject, section, classroom)

        // 1. DEPARTMENTS
        $dept1 = DB::table('departments')->insertGetId([
            'name' => 'Informatique', 'code' => 'INF', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $dept2 = DB::table('departments')->insertGetId([
            'name' => 'Mathématiques', 'code' => 'MATH', 'created_at' => $now, 'updated_at' => $now,
        ]);

        // 2. PROGRAMS (Filières)
        $prog1 = DB::table('programs')->insertGetId([
            'department_id' => $dept1, 'name' => 'Licence Informatique', 'code' => 'LIC-INF',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $prog2 = DB::table('programs')->insertGetId([
            'department_id' => $dept1, 'name' => 'Master IA', 'code' => 'MASTER-IA',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $prog3 = DB::table('programs')->insertGetId([
            'department_id' => $dept2, 'name' => 'Licence Mathématiques', 'code' => 'LIC-MATH',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // 3. SEMESTERS
        $sem1_1 = DB::table('semesters')->insertGetId([
            'program_id' => $prog1, 'name' => 'S1', 'number' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sem1_2 = DB::table('semesters')->insertGetId([
            'program_id' => $prog1, 'name' => 'S2', 'number' => 2,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sem2_1 = DB::table('semesters')->insertGetId([
            'program_id' => $prog2, 'name' => 'S1', 'number' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sem3_1 = DB::table('semesters')->insertGetId([
            'program_id' => $prog3, 'name' => 'S1', 'number' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // 4. MODULES (per program/semester)
        $mod1_1 = DB::table('modules')->insertGetId([
            'program_id' => $prog1, 'semester_id' => $sem1_1, 'name' => 'Introduction Programmation', 'code' => 'INF101',
            'weekly_hours' => 6, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $mod1_2 = DB::table('modules')->insertGetId([
            'program_id' => $prog1, 'semester_id' => $sem1_1, 'name' => 'Mathématiques Discrètes', 'code' => 'INF102',
            'weekly_hours' => 4, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $mod1_3 = DB::table('modules')->insertGetId([
            'program_id' => $prog1, 'semester_id' => $sem1_2, 'name' => 'Structures de Données', 'code' => 'INF201',
            'weekly_hours' => 5, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $mod2_1 = DB::table('modules')->insertGetId([
            'program_id' => $prog2, 'semester_id' => $sem2_1, 'name' => 'Machine Learning Avancé', 'code' => 'MAI301',
            'weekly_hours' => 6, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $mod3_1 = DB::table('modules')->insertGetId([
            'program_id' => $prog3, 'semester_id' => $sem3_1, 'name' => 'Algèbre Linéaire', 'code' => 'MATH101',
            'weekly_hours' => 4, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // 5. STUDENT GROUPS (per semester)
        $sg1_1 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem1_1, 'name' => 'Groupe A', 'student_count' => 30,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sg1_2 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem1_1, 'name' => 'Groupe B', 'student_count' => 28,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sg2_1 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem1_2, 'name' => 'Groupe A', 'student_count' => 30,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sg3_1 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem2_1, 'name' => 'Promo 2025', 'student_count' => 15,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sg4_1 = DB::table('student_groups')->insertGetId([
            'semester_id' => $sem3_1, 'name' => 'Groupe Unique', 'student_count' => 25,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // 6. USERS (login accounts)
        $admin = DB::table('users')->insertGetId([
            'name' => 'Admin Master', 'email' => 'admin@school.local', 'role' => 'super_admin',
            'password' => bcrypt('password'), 'department_id' => $dept1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $prof1_user = DB::table('users')->insertGetId([
            'name' => 'Dr. Alice Martin', 'email' => 'alice@school.local', 'role' => 'prof',
            'password' => bcrypt('password'), 'department_id' => $dept1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $prof2_user = DB::table('users')->insertGetId([
            'name' => 'Prof. Bob Chen', 'email' => 'bob@school.local', 'role' => 'prof',
            'password' => bcrypt('password'), 'department_id' => $dept1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $chef_user = DB::table('users')->insertGetId([
            'name' => 'Chef Département', 'email' => 'chef@school.local', 'role' => 'chef_departement',
            'password' => bcrypt('password'), 'department_id' => $dept1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // 7. TEACHERS (academic records, linked to users where they exist)
        $prof1 = DB::table('teachers')->insertGetId([
            'user_id' => $prof1_user, 'name' => 'Dr. Alice Martin', 'email' => 'alice@school.local',
            'phone' => '+212612345678', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $prof2 = DB::table('teachers')->insertGetId([
            'user_id' => $prof2_user, 'name' => 'Prof. Bob Chen', 'email' => 'bob@school.local',
            'phone' => '+212687654321', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $prof3 = DB::table('teachers')->insertGetId([
            'user_id' => null, 'name' => 'Prof. Carol White', 'email' => 'carol@school.local',
            'phone' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // 8. SUBJECTS (per semester, assigned to a teacher)
        $subj1 = DB::table('subjects')->insertGetId([
            'semester_id' => $sem1_1, 'teacher_id' => $prof1, 'name' => 'Introduction Programmation',
            'code' => 'INF101', 'sessions_per_week' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $subj2 = DB::table('subjects')->insertGetId([
            'semester_id' => $sem1_1, 'teacher_id' => $prof2, 'name' => 'Mathématiques Discrètes',
            'code' => 'INF102', 'sessions_per_week' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $subj3 = DB::table('subjects')->insertGetId([
            'semester_id' => $sem1_2, 'teacher_id' => $prof1, 'name' => 'Structures de Données',
            'code' => 'INF201', 'sessions_per_week' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $subj4 = DB::table('subjects')->insertGetId([
            'semester_id' => $sem2_1, 'teacher_id' => $prof3, 'name' => 'Machine Learning Avancé',
            'code' => 'MAI301', 'sessions_per_week' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $subj5 = DB::table('subjects')->insertGetId([
            'semester_id' => $sem3_1, 'teacher_id' => $prof2, 'name' => 'Algèbre Linéaire',
            'code' => 'MATH101', 'sessions_per_week' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // 9. SECTIONS (program subdivisions)
        $sec1 = DB::table('sections')->insertGetId([
            'program_id' => $prog1, 'name' => 'L1 Informatique 2025', 'capacity' => 60,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sec2 = DB::table('sections')->insertGetId([
            'program_id' => $prog2, 'name' => 'Master IA 2025', 'capacity' => 30,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sec3 = DB::table('sections')->insertGetId([
            'program_id' => $prog3, 'name' => 'L1 Mathématiques 2025', 'capacity' => 50,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // 10. DAYS (Monday-Friday)
        $dayMon = DB::table('days')->insertGetId([
            'name' => 'Monday', 'position' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $dayTue = DB::table('days')->insertGetId([
            'name' => 'Tuesday', 'position' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $dayWed = DB::table('days')->insertGetId([
            'name' => 'Wednesday', 'position' => 3, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $dayThu = DB::table('days')->insertGetId([
            'name' => 'Thursday', 'position' => 4, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $dayFri = DB::table('days')->insertGetId([
            'name' => 'Friday', 'position' => 5, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // 11. TIMESLOTS
        $ts1 = DB::table('timeslots')->insertGetId([
            'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $ts2 = DB::table('timeslots')->insertGetId([
            'starts_at' => '10:00', 'ends_at' => '12:00', 'position' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $ts3 = DB::table('timeslots')->insertGetId([
            'starts_at' => '13:00', 'ends_at' => '15:00', 'position' => 3, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $ts4 = DB::table('timeslots')->insertGetId([
            'starts_at' => '15:00', 'ends_at' => '17:00', 'position' => 4, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // 12. CLASSROOMS
        $room1 = DB::table('classrooms')->insertGetId([
            'name' => 'Amphi A', 'capacity' => 100, 'type' => 'amphitheatre', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $room2 = DB::table('classrooms')->insertGetId([
            'name' => 'Salle 101', 'capacity' => 40, 'type' => 'classroom', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $room3 = DB::table('classrooms')->insertGetId([
            'name' => 'Salle 102', 'capacity' => 40, 'type' => 'classroom', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $room4 = DB::table('classrooms')->insertGetId([
            'name' => 'Labo Info', 'capacity' => 30, 'type' => 'lab', 'created_at' => $now, 'updated_at' => $now,
        ]);

        // 13. SAMPLE TIMETABLE SESSIONS
        // Semester 1, Monday-Friday, Multiple timeslots, Unique (day, timeslot, teacher/subject/section)
        DB::table('timetable_sessions')->insert([
            [
                'subject_id' => $subj1, 'teacher_id' => $prof1, 'section_id' => $sec1,
                'classroom_id' => $room4, 'semester_id' => $sem1_1,
                'day_id' => $dayMon, 'timeslot_id' => $ts1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'subject_id' => $subj1, 'teacher_id' => $prof1, 'section_id' => $sec1,
                'classroom_id' => $room4, 'semester_id' => $sem1_1,
                'day_id' => $dayWed, 'timeslot_id' => $ts1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'subject_id' => $subj2, 'teacher_id' => $prof2, 'section_id' => $sec1,
                'classroom_id' => $room2, 'semester_id' => $sem1_1,
                'day_id' => $dayTue, 'timeslot_id' => $ts2, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'subject_id' => $subj2, 'teacher_id' => $prof2, 'section_id' => $sec1,
                'classroom_id' => $room2, 'semester_id' => $sem1_1,
                'day_id' => $dayThu, 'timeslot_id' => $ts2, 'created_at' => $now, 'updated_at' => $now,
            ],
            // Semester 2
            [
                'subject_id' => $subj3, 'teacher_id' => $prof1, 'section_id' => $sec1,
                'classroom_id' => $room3, 'semester_id' => $sem1_2,
                'day_id' => $dayMon, 'timeslot_id' => $ts3, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'subject_id' => $subj3, 'teacher_id' => $prof1, 'section_id' => $sec1,
                'classroom_id' => $room3, 'semester_id' => $sem1_2,
                'day_id' => $dayFri, 'timeslot_id' => $ts3, 'created_at' => $now, 'updated_at' => $now,
            ],
            // Master
            [
                'subject_id' => $subj4, 'teacher_id' => $prof3, 'section_id' => $sec2,
                'classroom_id' => $room1, 'semester_id' => $sem2_1,
                'day_id' => $dayMon, 'timeslot_id' => $ts4, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'subject_id' => $subj4, 'teacher_id' => $prof3, 'section_id' => $sec2,
                'classroom_id' => $room1, 'semester_id' => $sem2_1,
                'day_id' => $dayWed, 'timeslot_id' => $ts4, 'created_at' => $now, 'updated_at' => $now,
            ],
            // Math
            [
                'subject_id' => $subj5, 'teacher_id' => $prof2, 'section_id' => $sec3,
                'classroom_id' => $room2, 'semester_id' => $sem3_1,
                'day_id' => $dayTue, 'timeslot_id' => $ts1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'subject_id' => $subj5, 'teacher_id' => $prof2, 'section_id' => $sec3,
                'classroom_id' => $room2, 'semester_id' => $sem3_1,
                'day_id' => $dayThu, 'timeslot_id' => $ts1, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $this->command->info('✅ Demo data seeded successfully!');
        $this->command->line('  - 2 departments, 3 programs, 4 semesters');
        $this->command->line('  - 5 modules, 5 student groups, 4 users (1 admin, 2 profs, 1 chef)');
        $this->command->line('  - 3 teachers, 5 subjects, 3 sections');
        $this->command->line('  - 5 days, 4 timeslots, 4 classrooms');
        $this->command->line('  - 10 sample timetable sessions (ready for testing)');
        $this->command->line('');
        $this->command->line('Test logins:');
        $this->command->line('  admin@school.local (super_admin)');
        $this->command->line('  alice@school.local (prof, Dr. Alice Martin)');
        $this->command->line('  bob@school.local (prof, Prof. Bob Chen)');
        $this->command->line('  chef@school.local (chef_departement)');
        $this->command->line('  (all with password: "password")');
    }
}
