<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('prof')->after('password');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('program_id')->nullable();
        });
        Schema::create('academic_years', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->date('starts_on')->nullable(); $t->date('ends_on')->nullable(); $t->boolean('is_active')->default(false); $t->timestamps(); });
        Schema::create('departments', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->string('code')->unique(); $t->timestamps(); });
        Schema::create('programs', function (Blueprint $t) { $t->id(); $t->foreignId('department_id')->constrained()->cascadeOnDelete(); $t->string('name'); $t->string('code')->unique(); $t->timestamps(); });
        Schema::create('semesters', function (Blueprint $t) { $t->id(); $t->foreignId('program_id')->constrained()->cascadeOnDelete(); $t->foreignId('academic_year_id')->constrained()->cascadeOnDelete(); $t->string('name'); $t->unsignedTinyInteger('number'); $t->timestamps(); });
        Schema::create('modules', function (Blueprint $t) { $t->id(); $t->foreignId('program_id')->constrained()->cascadeOnDelete(); $t->string('name'); $t->string('code')->unique(); $t->unsignedSmallInteger('weekly_hours')->default(2); $t->timestamps(); });
        Schema::create('student_groups', function (Blueprint $t) { $t->id(); $t->foreignId('semester_id')->constrained()->cascadeOnDelete(); $t->string('name'); $t->unsignedInteger('student_count')->default(0); $t->timestamps(); });
        Schema::create('classrooms', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->unsignedInteger('capacity')->default(0); $t->string('type')->default('cours'); $t->timestamps(); });
        Schema::create('teaching_sessions', function (Blueprint $t) { $t->id(); $t->foreignId('semester_id')->constrained()->cascadeOnDelete(); $t->foreignId('module_id')->constrained()->cascadeOnDelete(); $t->foreignId('professor_id')->constrained('users')->cascadeOnDelete(); $t->foreignId('student_group_id')->constrained()->cascadeOnDelete(); $t->string('type')->default('cours'); $t->unsignedSmallInteger('duration_minutes')->default(120); $t->unsignedTinyInteger('occurrences_per_week')->default(1); $t->timestamps(); });
        Schema::create('professor_availabilities', function (Blueprint $t) { $t->id(); $t->foreignId('professor_id')->constrained('users')->cascadeOnDelete(); $t->unsignedTinyInteger('day_of_week'); $t->unsignedSmallInteger('start_minute'); $t->unsignedSmallInteger('end_minute'); $t->boolean('available')->default(true); $t->timestamps(); });
        Schema::create('schedules', function (Blueprint $t) { $t->id(); $t->foreignId('semester_id')->constrained()->cascadeOnDelete(); $t->string('name'); $t->string('status')->default('draft'); $t->timestamps(); });
        Schema::create('timetable_entries', function (Blueprint $t) { $t->id(); $t->foreignId('schedule_id')->constrained()->cascadeOnDelete(); $t->foreignId('teaching_session_id')->constrained()->cascadeOnDelete(); $t->foreignId('classroom_id')->constrained()->cascadeOnDelete(); $t->unsignedTinyInteger('day_of_week'); $t->unsignedSmallInteger('start_minute'); $t->unsignedSmallInteger('end_minute'); $t->unsignedTinyInteger('occurrence')->default(1); $t->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('timetable_entries'); Schema::dropIfExists('schedules'); Schema::dropIfExists('professor_availabilities'); Schema::dropIfExists('teaching_sessions'); Schema::dropIfExists('classrooms'); Schema::dropIfExists('student_groups'); Schema::dropIfExists('modules'); Schema::dropIfExists('semesters'); Schema::dropIfExists('programs'); Schema::dropIfExists('departments'); Schema::dropIfExists('academic_years'); Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role','department_id','program_id'])); }
};
