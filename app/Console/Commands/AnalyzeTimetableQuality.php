<?php

namespace App\Console\Commands;

use App\Services\AutoGenerateTimetable;
use App\Services\TimetableQualityAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeTimetableQuality extends Command
{
    protected $signature = 'timetable:quality {semester_id}';
    protected $description = 'Display generation statistics and timetable quality diagnostics for a semester.';

    public function handle(): int
    {
        $semesterId = (int) $this->argument('semester_id');
        $generator = app(AutoGenerateTimetable::class);
        $report = $generator->generate($semesterId);
        $analyzer = app(TimetableQualityAnalyzer::class);
        $analysis = $analyzer->analyze($semesterId, $report);

        $this->info($report['summary'] ?? 'No generation summary available.');
        $this->info('');
        $this->info('Hard conflicts:');
        $this->line($analysis['hard_conflicts'] ? implode(PHP_EOL, $analysis['hard_conflicts']) : 'None');
        $this->info('');
        $this->info('Soft warnings:');
        $this->line($analysis['soft_warnings'] ? implode(PHP_EOL, $analysis['soft_warnings']) : 'None');
        $this->info('');
        $this->info('Workload analysis:');
        $this->table(['Teacher', 'Sessions', 'Hours'], collect($analysis['teacher_workload'])->map(function ($entry, $teacherId) {
            $teacherName = DB::table('teachers')->find($teacherId)?->name ?? '#'.$teacherId;
            return [$teacherName, $entry['sessions'] ?? 0, $entry['hours'] ?? 0];
        })->values()->all());
        $this->info('');
        $this->info('Classroom utilization:');
        $this->table(['Classroom', 'Sessions', 'Hours'], collect($analysis['classroom_usage'])->map(function ($entry, $classroomId) {
            $name = DB::table('classrooms')->find($classroomId)?->name ?? '#'.$classroomId;
            return [$name, $entry['sessions'] ?? 0, $entry['hours'] ?? 0];
        })->values()->all());
        $this->info('');
        $this->info($analysis['quality_summary']);

        return self::SUCCESS;
    }
}
