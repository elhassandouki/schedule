<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('student_groups', function (Blueprint $t) { $t->foreignId('program_id')->nullable()->after('id')->constrained('programs')->nullOnDelete(); });
        // Keep the migration portable: the test suite uses SQLite while production
        // commonly uses MySQL.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE student_groups g INNER JOIN semesters s ON s.id = g.semester_id SET g.program_id = s.program_id WHERE g.program_id IS NULL');
        } else {
            DB::table('student_groups')->orderBy('id')->each(function ($group) {
                DB::table('student_groups')->where('id', $group->id)->update([
                    'program_id' => DB::table('semesters')->where('id', $group->semester_id)->value('program_id'),
                ]);
            });
        }
        Schema::create('group_study_conditions', function (Blueprint $t) { $t->id(); $t->foreignId('student_group_id')->constrained()->cascadeOnDelete(); $t->unsignedTinyInteger('day_of_week'); $t->unsignedSmallInteger('start_minute'); $t->unsignedSmallInteger('end_minute'); $t->unsignedSmallInteger('max_daily_minutes')->default(360); $t->unsignedSmallInteger('max_gap_minutes')->default(60); $t->timestamps(); $t->unique(['student_group_id','day_of_week']); });
    }
    public function down(): void { Schema::dropIfExists('group_study_conditions'); Schema::table('student_groups', function (Blueprint $t) { $t->dropConstrainedForeignId('program_id'); }); }
};
