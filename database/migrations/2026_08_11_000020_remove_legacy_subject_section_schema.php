<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Legacy rows cannot be displayed or regenerated because they have no
        // module/professor. New schedules are generated from the clean model.
        DB::table('timetable_sessions')->whereNull('module_id')->delete();

        foreach (['timetable_teacher_slot_unique', 'timetable_sessions_teacher_id_day_id_timeslot_id_index', 'timetable_sessions_subject_id_foreign', 'timetable_group_slot_unique', 'timetable_sessions_section_id_day_id_timeslot_id_index'] as $index) {
            try { DB::statement("ALTER TABLE timetable_sessions DROP INDEX {$index}"); } catch (\Throwable) {}
        }

        Schema::table('timetable_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('timetable_sessions', 'subject_id')) $table->dropColumn('subject_id');
            if (Schema::hasColumn('timetable_sessions', 'teacher_id')) $table->dropColumn('teacher_id');
            $table->unique(['professor_id', 'semester_id', 'day_id', 'timeslot_id'], 'timetable_professor_slot_unique');
        });

        // Old parallel data model, replaced by modules, student_groups and
        // timetable_sessions. Laravel infrastructure tables are deliberately kept.
        foreach (['timetable_entries', 'teaching_sessions', 'schedules', 'students', 'sections', 'subjects', 'teachers'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('The legacy scheduling schema was intentionally removed and cannot be restored automatically.');
    }
};
