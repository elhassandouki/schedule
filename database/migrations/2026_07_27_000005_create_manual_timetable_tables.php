<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(0);
            $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('timeslots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['starts_at', 'ends_at']);
        });
        Schema::create('days', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('position')->unique();
            $table->timestamps();
        });
        // Named timetable_sessions because Laravel uses the sessions table itself.
        Schema::create('timetable_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('timeslot_id')->constrained()->restrictOnDelete();
            $table->foreignId('day_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index(['teacher_id', 'day_id', 'timeslot_id']);
            $table->index(['classroom_id', 'day_id', 'timeslot_id']);
            $table->index(['section_id', 'day_id', 'timeslot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_sessions');
        Schema::dropIfExists('days');
        Schema::dropIfExists('timeslots');
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('teachers');
    }
};
