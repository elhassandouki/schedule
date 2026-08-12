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

        $isSqlite = DB::getDriverName() === 'sqlite';
        // On SQLite, dropColumn rewrites the whole table and fails if an
        // in-flight foreign key references the column being dropped.
        if ($isSqlite) {
            Schema::table('timetable_sessions', function (Blueprint $table) {
                $table->dropForeign(['subject_id']);
                $table->dropForeign(['teacher_id']);
                // timetable_sessions.section_id still references the sections
                // table that is dropped below; remove the FK first.
                if (Schema::hasColumn('timetable_sessions', 'section_id')) {
                    $table->dropForeign(['section_id']);
                }
            });
        }

        foreach (['timetable_teacher_slot_unique', 'timetable_sessions_teacher_id_day_id_timeslot_id_index', 'timetable_sessions_subject_id_foreign', 'timetable_group_slot_unique', 'timetable_sessions_section_id_day_id_timeslot_id_index', 'timetable_semester_group_index'] as $index) {
            try { Schema::table('timetable_sessions', fn (Blueprint $t) => $t->dropIndex($index)); } catch (\Throwable) {}
            try { DB::statement("DROP INDEX IF EXISTS `{$index}`"); } catch (\Throwable) {}
        }

        // In tests (SQLite) the legacy section_id column must also go, otherwise
        // it still carries a (now broken) FK towards the dropped sections table.
        // This must run AFTER the indexes referencing it were removed.
        if ($isSqlite && Schema::hasColumn('timetable_sessions', 'section_id')) {
            Schema::table('timetable_sessions', fn (Blueprint $table) => $table->dropColumn('section_id'));
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
