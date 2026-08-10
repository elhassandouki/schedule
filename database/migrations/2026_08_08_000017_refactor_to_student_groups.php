<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // SQLite does not support DROP FOREIGN KEY / DROP COLUMN with implicit
        // foreign references, so MySQL-specific operations are skipped there.
        $isMySql = DB::connection()->getDriverName() === 'mysql';

        // 1. Remove old section_id constraint from timetable_sessions
        Schema::table('timetable_sessions', function (Blueprint $table) use ($isMySql) {
            // Drop the foreign key constraint by name (MySQL only)
            if ($isMySql) {
                try {
                    DB::statement('ALTER TABLE timetable_sessions DROP FOREIGN KEY timetable_sessions_section_id_foreign');
                } catch (\Exception $e) {
                    // Constraint doesn't exist, that's fine
                }
            }

            // Drop section_id column if it exists.
            // SQLite cannot drop a column referenced by an implicit foreign
            // key, so this step is MySQL-only. On SQLite we make the unused
            // column nullable instead.
            if ($isMySql && Schema::hasColumn('timetable_sessions', 'section_id')) {
                $table->dropColumn('section_id');
            }
            if (!$isMySql && Schema::hasColumn('timetable_sessions', 'section_id')) {
                $table->unsignedBigInteger('section_id')->nullable()->change();
            }
        });

        // 2. Add student_group_id to timetable_sessions
        if (!Schema::hasColumn('timetable_sessions', 'student_group_id')) {
            Schema::table('timetable_sessions', function (Blueprint $table) {
                $table->foreignId('student_group_id')
                    ->after('semester_id')
                    ->constrained('student_groups')
                    ->cascadeOnDelete();
            });
        }

        // 3. Update subject table to NOT be tied to semester.
        // SQLite cannot drop a column referenced by an implicit foreign key,
        // so the column removal is MySQL-only (tests keep the nullable column).
        if ($isMySql && Schema::hasColumn('subjects', 'semester_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                try {
                    DB::statement('ALTER TABLE subjects DROP FOREIGN KEY subjects_semester_id_foreign');
                } catch (\Exception $e) {
                    // Constraint doesn't exist, that's fine
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
        Schema::table('timetable_sessions', function (Blueprint $table) use ($isMySql) {
            // Drop old constraint if it exists
            if ($isMySql) {
                try {
                    DB::statement('ALTER TABLE timetable_sessions DROP INDEX timetable_sessions_section_id_semester_id_day_id_timeslot_id_unique');
                } catch (\Exception $e) {
                    // Constraint doesn't exist, that's fine
                }
            }

            // Add new constraint using student_group_id (with shorter name)
            if (!$this->uniqueExists('timetable_sessions', 'ts_sg_sem_day_slot_unique')) {
                $table->unique(['student_group_id', 'semester_id', 'day_id', 'timeslot_id'], 'ts_sg_sem_day_slot_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            try {
                DB::statement('ALTER TABLE timetable_sessions DROP FOREIGN KEY timetable_sessions_student_group_id_foreign');
            } catch (\Exception $e) {
                // Constraint doesn't exist
            }
            
            if (Schema::hasColumn('timetable_sessions', 'student_group_id')) {
                $table->dropColumn('student_group_id');
            }
            
            if (!Schema::hasColumn('timetable_sessions', 'section_id')) {
                $table->foreignId('section_id')
                    ->constrained('sections')
                    ->cascadeOnDelete();
            }
        });

        if (Schema::hasTable('subjects')) {
            Schema::table('subjects', function (Blueprint $table) {
                if (!Schema::hasColumn('subjects', 'semester_id')) {
                    $table->foreignId('semester_id')
                        ->after('id')
                        ->constrained()
                        ->cascadeOnDelete();
                }
            });
        }

        if (Schema::hasTable('student_groups')) {
            Schema::table('student_groups', function (Blueprint $table) {
                if (Schema::hasColumn('student_groups', 'semester_id')) {
                    try {
                        DB::statement('ALTER TABLE student_groups DROP FOREIGN KEY student_groups_semester_id_foreign');
                    } catch (\Exception $e) {
                        // Constraint doesn't exist
                    }
                    $table->dropColumn('semester_id');
                }
            });
        }
    }

    private function uniqueExists($table, $constraint): bool
    {
        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $result = DB::select("PRAGMA index_list({$table})");
                return in_array($constraint, array_column($result, 'name'), true);
            }
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
