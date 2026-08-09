<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Remove old section_id constraint from timetable_sessions
        Schema::table('timetable_sessions', function (Blueprint $table) {
            // Drop the foreign key constraint by name
            if ($this->constraintExists('timetable_sessions', 'timetable_sessions_section_id_foreign')) {
                DB::statement('ALTER TABLE timetable_sessions DROP FOREIGN KEY timetable_sessions_section_id_foreign');
            }
            $table->dropColumnIfExists('section_id');
        });

        // 2. Add student_group_id to timetable_sessions
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->foreignId('student_group_id')
                ->after('semester_id')
                ->constrained('student_groups')
                ->cascadeOnDelete();
        });

        // 3. Update subject table to NOT be tied to semester
        if (Schema::hasColumn('subjects', 'semester_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                if ($this->constraintExists('subjects', 'subjects_semester_id_foreign')) {
                    DB::statement('ALTER TABLE subjects DROP FOREIGN KEY subjects_semester_id_foreign');
                }
                $table->dropColumn('semester_id');
            });
        }

        // 4. Ensure student_groups is tied to semester
        if (!Schema::hasColumn('student_groups', 'semester_id')) {
            Schema::table('student_groups', function (Blueprint $table) {
                $table->foreignId('semester_id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }

        // 5. Update unique constraint on timetable_sessions
        Schema::table('timetable_sessions', function (Blueprint $table) {
            // Drop old constraint if it exists
            if ($this->uniqueExists('timetable_sessions', 'timetable_sessions_section_id_semester_id_day_id_timeslot_id_unique')) {
                $table->dropUnique('timetable_sessions_section_id_semester_id_day_id_timeslot_id_unique');
            }
            
            // Add new constraint using student_group_id
            $table->unique(['student_group_id', 'semester_id', 'day_id', 'timeslot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            if ($this->constraintExists('timetable_sessions', 'timetable_sessions_student_group_id_foreign')) {
                DB::statement('ALTER TABLE timetable_sessions DROP FOREIGN KEY timetable_sessions_student_group_id_foreign');
            }
            $table->dropColumnIfExists('student_group_id');
            
            $table->foreignId('section_id')
                ->constrained('sections')
                ->cascadeOnDelete();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('semester_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        Schema::table('student_groups', function (Blueprint $table) {
            if (Schema::hasColumn('student_groups', 'semester_id')) {
                if ($this->constraintExists('student_groups', 'student_groups_semester_id_foreign')) {
                    DB::statement('ALTER TABLE student_groups DROP FOREIGN KEY student_groups_semester_id_foreign');
                }
                $table->dropColumn('semester_id');
            }
        });
    }

    private function constraintExists($table, $constraint): bool
    {
        try {
            $result = DB::select(
                'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
                [$table, $constraint]
            );
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function uniqueExists($table, $constraint): bool
    {
        try {
            $result = DB::select(
                'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
                [$table, $constraint]
            );
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
};
