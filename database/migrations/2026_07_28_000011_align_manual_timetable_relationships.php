<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('id')->constrained('programs')->nullOnDelete();
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->after('semester_id')->constrained()->nullOnDelete();
        });
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // These three rules make double-booking impossible even if an importer
            // or future UI accidentally bypasses application validation.
            $table->unique(['teacher_id', 'semester_id', 'day_id', 'timeslot_id'], 'timetable_teacher_slot_unique');
            $table->unique(['classroom_id', 'semester_id', 'day_id', 'timeslot_id'], 'timetable_classroom_slot_unique');
            $table->unique(['section_id', 'semester_id', 'day_id', 'timeslot_id'], 'timetable_group_slot_unique');
            $table->index(['semester_id', 'section_id'], 'timetable_semester_group_index');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->dropUnique('timetable_teacher_slot_unique');
            $table->dropUnique('timetable_classroom_slot_unique');
            $table->dropUnique('timetable_group_slot_unique');
            $table->dropIndex('timetable_semester_group_index');
            $table->dropConstrainedForeignId('semester_id');
        });
        Schema::table('subjects', function (Blueprint $table) { $table->dropConstrainedForeignId('teacher_id'); $table->dropConstrainedForeignId('semester_id'); });
        Schema::table('sections', function (Blueprint $table) { $table->dropConstrainedForeignId('program_id'); });
    }
};
