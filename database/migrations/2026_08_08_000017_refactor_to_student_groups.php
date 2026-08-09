<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Remove old section_id constraint from timetable_sessions
        Schema::table('timetable_sessions', function (Blueprint $table) {
            // Drop the old section constraint
            $table->dropForeignIdFor('Section');
            $table->dropColumn('section_id');
        });

        // 2. Add student_group_id to timetable_sessions
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->foreignId('student_group_id')
                ->after('semester_id')
                ->constrained('student_groups')
                ->cascadeOnDelete();
        });

        // 3. Update subject table to NOT be tied to semester
        // (if it has semester_id, remove it)
        if (Schema::hasColumn('subjects', 'semester_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropForeignIdFor('Semester');
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
        // Remove old section-based constraint, add student_group-based
        Schema::table('timetable_sessions', function (Blueprint $table) {
            // Drop old constraint if it exists
            try {
                $table->dropUnique(['section_id', 'semester_id', 'day_id', 'timeslot_id']);
            } catch (\Exception $e) {
                // Constraint doesn't exist, that's fine
            }
            
            // Add new constraint using student_group_id
            $table->unique(['student_group_id', 'semester_id', 'day_id', 'timeslot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->dropForeignIdFor('StudentGroup');
            $table->dropColumn('student_group_id');
            
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
                $table->dropForeignIdFor('Semester');
                $table->dropColumn('semester_id');
            }
        });
    }
};
