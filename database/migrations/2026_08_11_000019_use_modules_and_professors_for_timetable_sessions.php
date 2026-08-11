<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            // Keep legacy columns only for already-generated rows. New rows use
            // module_id and professor_id exclusively.
            $table->foreignId('subject_id')->nullable()->change();
            $table->foreignId('teacher_id')->nullable()->change();
            $table->foreignId('module_id')->nullable()->after('semester_id')->constrained()->nullOnDelete();
            $table->foreignId('professor_id')->nullable()->after('module_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('professor_id');
            $table->dropConstrainedForeignId('module_id');
        });
    }
};
