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
        ];

        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $this->line(str_pad($table, 26) . ': ' . $count);
            } catch (\Throwable $e) {
                $this->line(str_pad($table, 26) . ': ERROR (' . $e->getMessage() . ')');
            }
        }

        return self::SUCCESS;
    }
}
