<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Modify subjects table - remove semester_id, make teacher_id nullable
        Schema::table('subjects', function (Blueprint $table) {
            // First remove the foreign key if it exists
            try {
                $table->dropForeign(['semester_id']);
            } catch (\Exception $e) {
                // Foreign key doesn't exist, continue
            }
            
            try {
                $table->dropColumn('semester_id');
            } catch (\Exception $e) {
                // Column doesn't exist, continue
            }
        });

        // Make teacher_id nullable so it can be assigned later
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_id')->nullable()->change();
        });

        // 2. Drop old timetable_sessions and recreate with student_group_id
        Schema::dropIfExists('timetable_sessions');
        
        Schema::create('timetable_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('student_group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_id')->constrained('days')->cascadeOnDelete();
            $table->foreignId('timeslot_id')->constrained('timeslots')->cascadeOnDelete();
            $table->timestamps();

            // Constraints: prevent double-booking
            $table->unique(['teacher_id', 'day_id', 'timeslot_id', 'semester_id'], 
                'unique_teacher_slot_semester');
            $table->unique(['classroom_id', 'day_id', 'timeslot_id', 'semester_id'], 
                'unique_classroom_slot_semester');
            $table->unique(['student_group_id', 'day_id', 'timeslot_id', 'semester_id'], 
                'unique_group_slot_semester');

            $table->index(['semester_id', 'day_id', 'timeslot_id']);
            $table->index(['subject_id', 'semester_id']);
        });

        // 3. Update student_groups - ensure semester_id exists
        if (!Schema::hasColumn('student_groups', 'semester_id')) {
            Schema::table('student_groups', function (Blueprint $table) {
                $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            });
        }

        // 4. Add capacity to student_groups if missing
        if (!Schema::hasColumn('student_groups', 'capacity')) {
            Schema::table('student_groups', function (Blueprint $table) {
                $table->unsignedInteger('capacity')->default(0);
            });
        }
    }

    public function down(): void
    {
        // Recreate old structure
        Schema::dropIfExists('timetable_sessions');
        
        Schema::create('timetable_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_id')->constrained('days')->cascadeOnDelete();
            $table->foreignId('timeslot_id')->constrained('timeslots')->cascadeOnDelete();
            $table->timestamps();
        });

        // Restore semester_id to subjects
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
        });
    }
};
