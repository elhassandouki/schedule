<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->dropUnique('timetable_teacher_slot_unique');
            $table->dropUnique('timetable_classroom_slot_unique');
            $table->dropUnique('timetable_group_slot_unique');
            $table->unique(['teacher_id', 'semester_id', 'day_id', 'timeslot_id'], 'timetable_teacher_slot_unique');
            $table->unique(['classroom_id', 'semester_id', 'day_id', 'timeslot_id'], 'timetable_classroom_slot_unique');
            $table->unique(['section_id', 'semester_id', 'day_id', 'timeslot_id'], 'timetable_group_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->dropUnique('timetable_teacher_slot_unique');
            $table->dropUnique('timetable_classroom_slot_unique');
            $table->dropUnique('timetable_group_slot_unique');
            $table->unique(['teacher_id', 'day_id', 'timeslot_id'], 'timetable_teacher_slot_unique');
            $table->unique(['classroom_id', 'day_id', 'timeslot_id'], 'timetable_classroom_slot_unique');
            $table->unique(['section_id', 'day_id', 'timeslot_id'], 'timetable_group_slot_unique');
        });
    }
};
