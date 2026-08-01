<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseData extends Command
{
    protected $signature = 'timetable:diagnose';
    protected $description = 'Print row counts for every table relevant to the legacy/unified timetable migration.';

    public function handle(): int
    {
        $tables = [
            'departments', 'programs', 'semesters', 'modules', 'student_groups',
            'teaching_sessions', 'schedules', 'timetable_entries', 'professor_availabilities',
            'users', 'subjects', 'sections', 'teachers', 'timetable_sessions', 'days', 'timeslots', 'classrooms',
            'professor_module',
        ];

        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $this->line(str_pad($table, 26) . ': ' . $count);
            } catch (\Throwable $e) {
                $this->line(str_pad($table, 26) . ': ERROR (' . $e->getMessage() . ')');
            }
        }

        $totalModules = DB::table('modules')->count();
        $modulesWithTeacher = DB::table('professor_module')->distinct('module_id')->count('module_id');
        $this->newLine();
        $this->line("modules WITH a professor_module assignment      : {$modulesWithTeacher}");
        $this->line("modules WITHOUT any assignment (missing teacher): " . ($totalModules - $modulesWithTeacher));

        return self::SUCCESS;
    }
}
